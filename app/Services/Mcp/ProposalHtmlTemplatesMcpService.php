<?php

namespace App\Services\Mcp;

use App\Models\ProposalHtmlTemplate;
use App\Models\User;
use App\Support\ProposalHtmlEmailDocumentNormalizer;
use App\Support\ProposalHtmlManagerContactNormalizer;
use App\Support\ProposalHtmlTemplateColdEmailLibrary;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProposalHtmlTemplatesMcpService
{
    public function requireManageAccess(User $user): void
    {
        if (! RoleAccess::canAccessSettingsSystem($user)) {
            throw new AuthenticationException(
                'Шаблоны HTML-КП доступны администратору или области settings_system.',
            );
        }
    }

    /**
     * @return array{items: list<array<string, mixed>>, stock_assets: list<string>, edit_base_path: string}
     */
    public function list(?string $query = null, int $limit = 50): array
    {
        $this->assertTable();

        $limit = max(1, min($limit, 100));
        $query = $this->normalizeOptional($query);

        $builder = ProposalHtmlTemplate::query()->orderBy('name');

        if ($query !== null) {
            $builder->where(function ($inner) use ($query): void {
                $inner->where('name', 'like', '%'.$query.'%')
                    ->orWhere('slug', 'like', '%'.$query.'%');
            });
        }

        $items = $builder
            ->limit($limit)
            ->get()
            ->map(fn (ProposalHtmlTemplate $template): array => $this->serializeSummary($template))
            ->values()
            ->all();

        return [
            'items' => $items,
            'stock_assets' => ProposalHtmlTemplateColdEmailLibrary::stockAssetFilenames(),
            'edit_base_path' => '/modules/proposal-templates',
            'hint' => 'Для красивого rich-КП клонируйте base_slug=parallel-import и подставьте тексты/картинки. Для короткого холодного письма — mode=cold.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int|string $idOrSlug, bool $includeHtml = false): array
    {
        $this->assertTable();
        $template = $this->findTemplate($idOrSlug);

        $payload = $this->serializeSummary($template);
        $html = (string) $template->html_body;
        $payload['placeholders'] = $this->extractPlaceholders($html);
        $payload['image_srcs'] = $this->extractImageSrcs($html);
        $payload['has_mailto_manager'] = str_contains($html, 'mailto:{manager.email}');
        $payload['css_inline_bytes'] = strlen((string) ($template->css_inline ?? ''));
        $payload['email_assets'] = is_array($template->email_assets) ? $template->email_assets : [];
        $payload['edit_path'] = '/modules/proposal-templates/'.$template->id.'/edit';

        if ($includeHtml) {
            $payload['html_body'] = $html;
            $payload['css_inline'] = $template->css_inline;
        } else {
            $payload['html_preview'] = Str::limit(strip_tags($html), 400);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(User $user, array $input): array
    {
        $this->assertTable();

        $mode = (string) ($input['mode'] ?? 'cold');
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Укажите name шаблона.');
        }

        $slug = $this->uniqueSlug(
            filled($input['slug'] ?? null)
                ? (string) $input['slug']
                : $name
        );

        if ($mode === 'clone') {
            $baseSlug = trim((string) ($input['base_slug'] ?? 'parallel-import'));
            $base = ProposalHtmlTemplate::query()->where('slug', $baseSlug)->first()
                ?? ProposalHtmlTemplate::query()->where('slug', 'parallel-import-demo')->first();

            if ($base === null) {
                throw new InvalidArgumentException(
                    'Базовый шаблон «'.$baseSlug.'» не найден. Сначала list_proposal_html_templates.',
                );
            }

            $html = (string) $base->html_body;
            $css = $base->css_inline;
            $assets = is_array($base->email_assets) ? $base->email_assets : [];

            $html = $this->applyTextReplacements($html, is_array($input['text_replacements'] ?? null) ? $input['text_replacements'] : []);
            $html = $this->applyImageReplacements($html, $slug, is_array($input['images'] ?? null) ? $input['images'] : []);
            $html = ProposalHtmlManagerContactNormalizer::normalize($html);
            $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html);

            $template = ProposalHtmlTemplate::query()->create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => (bool) ($input['is_active'] ?? true),
                'html_body' => $normalized['body'],
                'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : $css,
                'email_assets' => $assets,
                'version' => 1,
                'published_at' => now(),
                'owner_user_id' => $user->id,
                'visibility' => 'company',
            ]);

            return $this->createdPayload($template, 'clone', $base->slug);
        }

        $points = $this->normalizePoints($input['points'] ?? []);
        if ($points === []) {
            $points = ['подберём маршрут и ставку под задачу;', 'держим связь по статусу груза;', 'считаем прозрачно, без скрытых доплат.'];
        }

        $heroAsset = $this->resolveColdAsset(
            $slug,
            (string) ($input['hero_image'] ?? ($input['stock_asset'] ?? 'route.svg')),
        );

        $built = ProposalHtmlTemplateColdEmailLibrary::buildCustom(
            preheader: (string) ($input['preheader'] ?? $name),
            title: (string) ($input['title'] ?? $name),
            intro: (string) ($input['intro'] ?? 'Пишу коротко: помогаем с перевозками и логистикой.'),
            angle: (string) ($input['angle'] ?? 'Можем быстро проверить маршрут, сроки и стоимость.'),
            points: $points,
            cta: (string) ($input['cta'] ?? 'Если актуально — пришлите маршрут и параметры груза.'),
            asset: $heroAsset,
        );

        $html = ProposalHtmlManagerContactNormalizer::normalize($built['html_body']);
        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html);

        $template = ProposalHtmlTemplate::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => (bool) ($input['is_active'] ?? true),
            'html_body' => $normalized['body'],
            'css_inline' => $normalized['css'] !== '' ? $normalized['css'] : $built['css_inline'],
            'email_assets' => [],
            'version' => 1,
            'published_at' => now(),
            'owner_user_id' => $user->id,
            'visibility' => 'company',
        ]);

        return $this->createdPayload($template, 'cold', null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(int|string $idOrSlug, array $input): array
    {
        $this->assertTable();
        $template = $this->findTemplate($idOrSlug);

        $html = (string) $template->html_body;
        $changed = false;

        if (filled($input['name'] ?? null)) {
            $template->name = trim((string) $input['name']);
            $changed = true;
        }

        if (array_key_exists('is_active', $input)) {
            $template->is_active = (bool) $input['is_active'];
            $changed = true;
        }

        if (is_array($input['text_replacements'] ?? null) && $input['text_replacements'] !== []) {
            $html = $this->applyTextReplacements($html, $input['text_replacements']);
            $changed = true;
        }

        if (is_array($input['images'] ?? null) && $input['images'] !== []) {
            $html = $this->applyImageReplacements($html, (string) $template->slug, $input['images']);
            $changed = true;
        }

        if (is_string($input['html_body'] ?? null) && trim((string) $input['html_body']) !== '') {
            $html = (string) $input['html_body'];
            $changed = true;
        }

        if ($changed) {
            $html = ProposalHtmlManagerContactNormalizer::normalize($html);
            $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html);
            $template->html_body = $normalized['body'];
            if ($normalized['css'] !== '') {
                $template->css_inline = $normalized['css'];
            } elseif (is_string($input['css_inline'] ?? null)) {
                $template->css_inline = (string) $input['css_inline'];
            }
            $template->version = (int) $template->version + 1;
            $template->save();
        }

        return [
            'ok' => true,
            'changed' => $changed,
            'template' => $this->get($template->id, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createdPayload(ProposalHtmlTemplate $template, string $mode, ?string $baseSlug): array
    {
        return [
            'ok' => true,
            'mode' => $mode,
            'base_slug' => $baseSlug,
            'template' => $this->get($template->id, false),
            'edit_url_hint' => 'Откройте /modules/proposal-templates/'.$template->id.'/edit для визуальной правки в GrapesJS.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSummary(ProposalHtmlTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => (string) $template->name,
            'slug' => (string) $template->slug,
            'is_active' => (bool) $template->is_active,
            'version' => (int) $template->version,
            'html_bytes' => strlen((string) $template->html_body),
            'published_at' => optional($template->published_at)?->toIso8601String(),
        ];
    }

    private function findTemplate(int|string $idOrSlug): ProposalHtmlTemplate
    {
        $key = is_numeric($idOrSlug) ? (int) $idOrSlug : trim((string) $idOrSlug);

        $template = is_int($key)
            ? ProposalHtmlTemplate::query()->find($key)
            : ProposalHtmlTemplate::query()->where('slug', $key)->first();

        if ($template === null) {
            throw new InvalidArgumentException('Шаблон КП не найден: '.$idOrSlug);
        }

        return $template;
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'proposal-'.Str::lower(Str::random(6));
        }

        $candidate = $base;
        $i = 2;
        while (ProposalHtmlTemplate::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    private function applyTextReplacements(string $html, array $replacements): string
    {
        foreach ($replacements as $from => $to) {
            if (! is_string($from) || $from === '' || ! is_scalar($to)) {
                continue;
            }
            $html = str_replace($from, (string) $to, $html);
        }

        return $html;
    }

    /**
     * @param  list<array{find?: string, url?: string, slot?: string}>|array<string, mixed>  $images
     */
    private function applyImageReplacements(string $html, string $slug, array $images): string
    {
        $srcs = $this->extractImageSrcs($html);
        $index = 0;

        foreach ($images as $key => $image) {
            if (is_string($image) && is_string($key)) {
                $image = ['find' => $key, 'url' => $image];
            }

            if (! is_array($image)) {
                continue;
            }

            $url = trim((string) ($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $publicPath = $this->materializeImage($slug, $url, (string) ($image['filename'] ?? 'img-'.$index));
            $find = trim((string) ($image['find'] ?? ''));

            if ($find === '' && isset($srcs[$index])) {
                $find = $srcs[$index];
            }

            if ($find !== '') {
                $html = str_replace($find, $publicPath, $html);
            }

            $index++;
        }

        return $html;
    }

    private function resolveColdAsset(string $slug, string $asset): string
    {
        $asset = trim($asset);
        if ($asset === '') {
            return 'route.svg';
        }

        $stock = ProposalHtmlTemplateColdEmailLibrary::stockAssetFilenames();
        if (in_array($asset, $stock, true)) {
            return $asset;
        }

        if (str_starts_with($asset, '/assets/proposal-emails/') && ! str_contains($asset, '..')) {
            return $asset;
        }

        return $this->materializeImage($slug, $asset, 'hero');
    }

    private function materializeImage(string $slug, string $source, string $filenameHint): string
    {
        $slug = Str::slug($slug) ?: 'proposal';
        $dirRelative = 'assets/proposal-emails/'.$slug;
        $dirAbsolute = public_path($dirRelative);
        File::ensureDirectoryExists($dirAbsolute);

        $ext = pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION) ?: 'png';
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?: 'png');
        $filename = Str::slug(pathinfo($filenameHint, PATHINFO_FILENAME) ?: 'img').'-'.Str::lower(Str::random(6)).'.'.$ext;
        $absolute = $dirAbsolute.DIRECTORY_SEPARATOR.$filename;
        $publicPath = '/'.$dirRelative.'/'.$filename;

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            $response = Http::timeout(20)->get($source);
            if (! $response->successful()) {
                throw new InvalidArgumentException('Не удалось скачать картинку: '.$source);
            }
            file_put_contents($absolute, $response->body());

            return $publicPath;
        }

        $local = $source;
        if (str_starts_with($local, '/')) {
            $local = public_path(ltrim($local, '/'));
        }

        if (! is_file($local)) {
            throw new InvalidArgumentException('Файл картинки не найден: '.$source);
        }

        File::copy($local, $absolute);

        return $publicPath;
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $html): array
    {
        preg_match_all('/\{([a-z0-9_.]+)\}/i', $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private function extractImageSrcs(string $html): array
    {
        preg_match_all('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/iu', $html, $matches);

        return array_values(array_filter($matches[2] ?? []));
    }

    /**
     * @return list<string>
     */
    private function normalizePoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $out = [];
        foreach ($points as $point) {
            if (! is_scalar($point)) {
                continue;
            }
            $trimmed = trim((string) $point);
            if ($trimmed !== '') {
                $out[] = $trimmed;
            }
        }

        return array_values($out);
    }

    private function normalizeOptional(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function assertTable(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            throw new InvalidArgumentException('Таблица proposal_html_templates недоступна.');
        }
    }
}
