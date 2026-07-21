<?php

namespace App\Services\PrintForm;

use App\Models\Contractor;
use App\Support\PrintFormPlaceholderPathResolver;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Черновик DOCX → предложения плейсхолдеров → применение в файл.
 *
 * v1: реквизиты своей компании / контрагента + правки известных опечаток
 * и дубля даты погрузки на блоке выгрузки. Человек подтверждает в UI.
 */
class PrintFormDraftPlaceholderConverter
{
    public const DISK = 'local';

    public const TMP_DIR = 'tmp/print-form-drafts';

    public function __construct(
        private readonly PrintFormPlaceholderPathResolver $pathResolver,
    ) {}

    /**
     * @return array{
     *     draft_token: string,
     *     original_filename: string,
     *     existing_placeholders: list<string>,
     *     proposals: list<array{
     *         id: string,
     *         find: string,
     *         replace: string,
     *         placeholder: string,
     *         path: string,
     *         confidence: string,
     *         reason: string,
     *         enabled: bool
     *     }>
     * }
     */
    public function analyze(
        string $absoluteDocxPath,
        string $originalFilename,
        string $party,
        ?Contractor $counterparty = null,
        ?Contractor $ownCompany = null,
    ): array {
        $token = (string) Str::uuid();
        $storedPath = self::TMP_DIR.'/'.$token.'.docx';
        Storage::disk(self::DISK)->put($storedPath, (string) file_get_contents($absoluteDocxPath));

        $plain = $this->extractPlainText($absoluteDocxPath);
        $existing = $this->extractPlaceholders($plain);
        $proposals = [];

        $proposals = array_merge(
            $proposals,
            $this->proposePlaceholderTypos($plain),
            $this->proposeDuplicateUnloadDatetime($plain),
            $this->proposePartyValueReplacements($plain, $party, $ownCompany, $counterparty),
        );

        $proposals = $this->dedupeProposals($proposals);

        return [
            'draft_token' => $token,
            'original_filename' => $originalFilename,
            'existing_placeholders' => $existing,
            'proposals' => array_values($proposals),
        ];
    }

    /**
     * @param  list<array{find?: string, replace?: string, enabled?: bool}>  $replacements
     */
    public function apply(string $draftToken, array $replacements): string
    {
        $storedPath = self::TMP_DIR.'/'.$draftToken.'.docx';
        $absolute = Storage::disk(self::DISK)->path($storedPath);

        if (! is_file($absolute)) {
            throw new \InvalidArgumentException('Черновик не найден или истёк. Загрузите файл снова.');
        }

        $outRelative = self::TMP_DIR.'/'.$draftToken.'-out.docx';
        $outAbsolute = Storage::disk(self::DISK)->path($outRelative);
        Storage::disk(self::DISK)->makeDirectory(self::TMP_DIR);
        copy($absolute, $outAbsolute);

        $pairs = [];
        foreach ($replacements as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['enabled'] ?? true) === false) {
                continue;
            }
            $find = trim((string) ($row['find'] ?? ''));
            $replace = (string) ($row['replace'] ?? '');
            if ($find === '' || $find === $replace) {
                continue;
            }
            $pairs[$find] = $replace;
        }

        // Longer needles first — avoid partial collisions (ИНН vs ИНН/КПП).
        uksort($pairs, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $this->replaceInDocx($outAbsolute, $pairs);

        return $outAbsolute;
    }

    public function forget(string $draftToken): void
    {
        foreach ([
            self::TMP_DIR.'/'.$draftToken.'.docx',
            self::TMP_DIR.'/'.$draftToken.'-out.docx',
        ] as $path) {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
    }

    /**
     * @return list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>
     */
    private function proposePlaceholderTypos(string $plain): array
    {
        $fixes = [
            '${kontankt_pogruzka}' => [
                'replace' => '${kontakt_na_zagruzke}',
                'placeholder' => 'kontakt_na_zagruzke',
                'reason' => 'Исправление опечатки в плейсхолдере контакта на погрузке',
            ],
            '${gruzootpavitel}' => [
                'replace' => '${gruzootpravitel}',
                'placeholder' => 'gruzootpravitel',
                'reason' => 'Нормализация имени плейсхолдера грузоотправителя',
            ],
        ];

        $out = [];
        foreach ($fixes as $find => $meta) {
            if (! str_contains($plain, $find)) {
                continue;
            }
            $out[] = $this->proposal(
                $find,
                $meta['replace'],
                $meta['placeholder'],
                'high',
                $meta['reason'],
            );
        }

        return $out;
    }

    /**
     * @return list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>
     */
    private function proposeDuplicateUnloadDatetime(string $plain): array
    {
        $needle = '${data_zagruzki}, ${vremya_zagruzki}';
        $count = substr_count($plain, $needle);
        if ($count < 2) {
            // Also accept split order / missing comma variants later if needed.
            $alt = '${data_zagruzki}, ${vremya_zagruzki}';
            $count = substr_count($plain, $alt);
        }
        if ($count < 2) {
            return [];
        }

        // Paragraph-level apply will replace ALL occurrences — so we use a unique second-pass marker approach:
        // suggest replacing the combined string only once via a dedicated apply step that targets the 2nd hit.
        // For proposals UI we still show one row; apply() handles nth occurrence via special find key.
        return [
            $this->proposal(
                '@@nth:2@@'.$needle,
                '${data_vygruzki}, ${vremya_vygruzki}',
                'data_vygruzki',
                'high',
                'Второй блок «дата/время подачи ТС» — выгрузка, не погрузка',
            ),
        ];
    }

    /**
     * @return list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>
     */
    private function proposePartyValueReplacements(
        string $plain,
        string $party,
        ?Contractor $ownCompany,
        ?Contractor $counterparty,
    ): array {
        $out = [];

        // Customer form: our company = Перевозчик (lp_), counterparty = Заказчик (cp_).
        // Carrier form: our company = Заказчик/своя сторона часто всё ещё lp_; внешний перевозчик = dp_.
        $ownPrefix = 'lp_';
        $counterPrefix = $party === 'carrier' ? 'dp_' : 'cp_';
        $ownRoot = 'own_company';
        $counterRoot = $party === 'carrier' ? 'carrier' : 'customer';

        if ($ownCompany !== null) {
            $out = array_merge($out, $this->proposalsForContractor($plain, $ownCompany, $ownPrefix, $ownRoot, 'Своя компания'));
        }
        if ($counterparty !== null) {
            $out = array_merge($out, $this->proposalsForContractor($plain, $counterparty, $counterPrefix, $counterRoot, 'Контрагент формы'));
        }

        return $out;
    }

    /**
     * @return list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>
     */
    private function proposalsForContractor(
        string $plain,
        Contractor $contractor,
        string $prefix,
        string $root,
        string $label,
    ): array {
        $bank = $contractor->bankDetailsFromAccountsFallback();
        $candidates = [
            ['values' => array_filter([$contractor->full_name, $contractor->name]), 'suffix' => 'nazv', 'path' => "{$root}.name"],
            ['values' => array_filter([$contractor->inn]), 'suffix' => 'inn', 'path' => "{$root}.inn"],
            ['values' => array_filter([$contractor->kpp]), 'suffix' => 'kpp', 'path' => "{$root}.kpp"],
            ['values' => array_filter([$contractor->ogrn]), 'suffix' => 'ogrn', 'path' => "{$root}.ogrn"],
            ['values' => array_filter([$contractor->legal_address]), 'suffix' => 'yur_address', 'path' => "{$root}.legal_address"],
            ['values' => array_filter([$contractor->postal_address, $contractor->actual_address]), 'suffix' => 'pocht_address', 'path' => "{$root}.postal_address"],
            ['values' => array_filter([$bank['bank_name'] ?? null, $contractor->bank_name]), 'suffix' => 'bank', 'path' => "{$root}.bank_name"],
            ['values' => array_filter([$bank['bik'] ?? null, $contractor->bik]), 'suffix' => 'bik', 'path' => "{$root}.bik"],
            ['values' => array_filter([$bank['account_number'] ?? null, $contractor->account_number]), 'suffix' => 'rs', 'path' => "{$root}.account_number"],
            ['values' => array_filter([$bank['correspondent_account'] ?? null, $contractor->correspondent_account]), 'suffix' => 'ks', 'path' => "{$root}.correspondent_account"],
            ['values' => array_filter([$contractor->signer_name_nominative]), 'suffix' => 'ceo', 'path' => "{$root}.signer_name_nominative"],
            ['values' => array_filter([$contractor->signer_authority_basis]), 'suffix' => 'osnovanie', 'path' => "{$root}.signer_authority_basis"],
            ['values' => array_filter([$contractor->phone]), 'suffix' => null, 'path' => "{$root}.phone", 'dotted' => true],
            ['values' => array_filter([$contractor->email]), 'suffix' => null, 'path' => "{$root}.email", 'dotted' => true],
        ];

        $out = [];
        foreach ($candidates as $candidate) {
            $placeholder = isset($candidate['dotted']) && $candidate['dotted'] === true
                ? (string) $candidate['path']
                : $prefix.(string) $candidate['suffix'];

            foreach ($candidate['values'] as $value) {
                $value = trim((string) $value);
                if ($value === '' || mb_strlen($value) < 3) {
                    continue;
                }
                if (! $this->plainContainsValue($plain, $value)) {
                    continue;
                }
                // Skip if already a placeholder for this value nearby — still replace raw text.
                $out[] = $this->proposal(
                    $value,
                    '${'.$placeholder.'}',
                    $placeholder,
                    mb_strlen($value) >= 8 ? 'high' : 'medium',
                    "{$label}: найдено значение «{$value}» → \${{$placeholder}}",
                    (string) $candidate['path'],
                );
            }
        }

        return $out;
    }

    private function plainContainsValue(string $plain, string $value): bool
    {
        $compactPlain = $this->compact($plain);
        $compactValue = $this->compact($value);
        if ($compactValue === '' || mb_strlen($compactValue) < 3) {
            return false;
        }

        return str_contains($plain, $value) || str_contains($compactPlain, $compactValue);
    }

    private function compact(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    /**
     * @param  list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>  $proposals
     * @return list<array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}>
     */
    private function dedupeProposals(array $proposals): array
    {
        $seen = [];
        $out = [];
        foreach ($proposals as $proposal) {
            $key = $proposal['find'].'→'.$proposal['replace'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $proposal;
        }

        usort($out, function (array $a, array $b): int {
            $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

            return ($rank[$a['confidence']] ?? 9) <=> ($rank[$b['confidence']] ?? 9)
                ?: mb_strlen($b['find']) <=> mb_strlen($a['find']);
        });

        return $out;
    }

    /**
     * @return array{id: string, find: string, replace: string, placeholder: string, path: string, confidence: string, reason: string, enabled: bool}
     */
    private function proposal(
        string $find,
        string $replace,
        string $placeholder,
        string $confidence,
        string $reason,
        ?string $path = null,
    ): array {
        $resolved = $path ?? $this->pathResolver->resolve($placeholder, [], 'order', 'customer');

        return [
            'id' => (string) Str::uuid(),
            'find' => $find,
            'replace' => $replace,
            'placeholder' => $placeholder,
            'path' => $resolved,
            'confidence' => $confidence,
            'reason' => $reason,
            'enabled' => true,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $plain): array
    {
        preg_match_all('/\$\{([^}]+)\}/u', $plain, $m);

        return collect($m[1] ?? [])
            ->map(fn (string $v): string => trim($v))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function extractPlainText(string $absoluteDocxPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($absoluteDocxPath) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            return '';
        }
        $text = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $text = strip_tags($text);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param  array<string, string>  $pairs  find => replace (find may be @@nth:N@@prefix)
     */
    private function replaceInDocx(string $absolutePath, array $pairs): void
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Не удалось открыть DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml) || $xml === '') {
            $zip->close();
            throw new \RuntimeException('В DOCX нет word/document.xml.');
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $nthCounters = [];

        foreach ($xpath->query('//w:p') as $para) {
            /** @var DOMElement $para */
            $nodes = [];
            $joined = '';
            foreach ($xpath->query('.//w:t', $para) as $t) {
                $nodes[] = $t;
                $joined .= $t->textContent;
            }
            if ($nodes === [] || $joined === '') {
                continue;
            }

            $original = $joined;
            foreach ($pairs as $find => $replace) {
                if (str_starts_with($find, '@@nth:')) {
                    if (! preg_match('/^@@nth:(\d+)@@(.+)$/u', $find, $m)) {
                        continue;
                    }
                    $n = (int) $m[1];
                    $needle = $m[2];
                    if ($needle === '' || ! str_contains($joined, $needle)) {
                        continue;
                    }

                    $offset = 0;
                    $needleLen = mb_strlen($needle);
                    while (($pos = mb_strpos($joined, $needle, $offset)) !== false) {
                        $nthCounters[$needle] = ($nthCounters[$needle] ?? 0) + 1;
                        if ($nthCounters[$needle] === $n) {
                            $joined = mb_substr($joined, 0, $pos)
                                .$replace
                                .mb_substr($joined, $pos + $needleLen);

                            break;
                        }
                        $offset = $pos + $needleLen;
                    }

                    continue;
                }

                if (str_contains($joined, $find)) {
                    $joined = str_replace($find, $replace, $joined);
                }
            }

            if ($joined === $original) {
                continue;
            }

            $nodes[0]->nodeValue = $joined;
            for ($i = 1, $c = count($nodes); $i < $c; $i++) {
                $nodes[$i]->nodeValue = '';
            }
        }

        $zip->addFromString('word/document.xml', (string) $dom->saveXML());
        $zip->close();
    }
}
