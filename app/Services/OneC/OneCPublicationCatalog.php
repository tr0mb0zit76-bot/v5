<?php

declare(strict_types=1);

namespace App\Services\OneC;

use App\Models\Order;
use App\Models\User;
use InvalidArgumentException;

/**
 * Каталог публикаций 1С БП (юрлица CRM).
 *
 * @phpstan-type Publication array{
 *     code: string,
 *     label: string,
 *     base_url: string,
 *     organization_ref: string,
 *     organization_inn: string,
 *     bank_account_number: string,
 *     service_nomenclature_ref: string,
 *     service_nomenclature_code: string,
 *     currency_ref: string,
 *     date_filter_mode: 'odata'|'client',
 *     enabled: bool,
 *     include_in_sync: bool
 * }
 */
final class OneCPublicationCatalog
{
    public const CODE_AUTALLIANCE = 'autalliance';

    public const CODE_GROSS = 'gross';

    public const CODE_PROFSFERA = 'profsfera';

    /** Тестовая ИБ Автоальянс (только push ЭПД по user override). */
    public const CODE_SANDBOX = 'sandbox';

    /**
     * Публикации для hourly sync (банк, реестр ЭПД и т.п.) — без sandbox.
     *
     * @return list<Publication>
     */
    public function all(): array
    {
        $rows = array_values(array_filter(
            $this->configuredEnabled(),
            static fn (array $pub): bool => $pub['include_in_sync'],
        ));

        return $rows !== [] ? $rows : [$this->legacyDefault()];
    }

    /**
     * Любая включённая публикация по коду (включая sandbox).
     *
     * @return Publication
     */
    public function get(string $code): array
    {
        foreach ($this->configuredEnabled() as $pub) {
            if ($pub['code'] === $code) {
                return $pub;
            }
        }

        throw new InvalidArgumentException("Неизвестная публикация 1С: {$code}");
    }

    public function defaultCode(): string
    {
        $code = (string) config('one_c.default_publication', self::CODE_AUTALLIANCE);

        try {
            $this->get($code);

            return $code;
        } catch (InvalidArgumentException) {
            $all = $this->all();

            return $all[0]['code'] ?? self::CODE_AUTALLIANCE;
        }
    }

    /**
     * Публикация по ИНН собственной организации CRM.
     *
     * @return Publication|null
     */
    public function forOrganizationInn(string $inn): ?array
    {
        $digits = preg_replace('/\D+/', '', $inn) ?? '';
        if ($digits === '') {
            return null;
        }

        foreach ($this->all() as $pub) {
            if ($pub['organization_inn'] !== '' && $pub['organization_inn'] === $digits) {
                return $pub;
            }
        }

        return null;
    }

    /**
     * ИБ по «Нашей компании» заказа; иначе default_publication.
     *
     * @return Publication
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['ownCompany:id,inn,name']);
        $own = $order->ownCompany;
        if ($own !== null) {
            $matched = $this->forOrganizationInn((string) ($own->inn ?? ''));
            if ($matched !== null) {
                return $matched;
            }
        }

        return $this->get($this->defaultCode());
    }

    /**
     * ИБ для push ЭПД (ЭТрН / ЭР): override пользователя → иначе forOrder.
     *
     * @return Publication
     */
    public function forEpdOrder(Order $order, ?User $actor = null): array
    {
        $override = trim((string) ($actor?->one_c_epd_publication_override ?? ''));
        if ($override !== '') {
            return $this->get($override);
        }

        return $this->forOrder($order);
    }

    /**
     * @return list<Publication>
     */
    private function configuredEnabled(): array
    {
        $configured = config('one_c.publications', []);
        if (! is_array($configured) || $configured === []) {
            return [$this->legacyDefault()];
        }

        $rows = [];
        foreach ($configured as $code => $row) {
            if (! is_array($row)) {
                continue;
            }
            $pub = $this->normalize((string) $code, $row);
            if ($pub['enabled'] && $pub['base_url'] !== '') {
                $rows[] = $pub;
            }
        }

        return $rows !== [] ? $rows : [$this->legacyDefault()];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Publication
     */
    private function normalize(string $code, array $row): array
    {
        $mode = (string) ($row['date_filter_mode'] ?? 'odata');
        if (! in_array($mode, ['odata', 'client'], true)) {
            $mode = 'odata';
        }

        $inn = preg_replace('/\D+/', '', (string) ($row['organization_inn'] ?? '')) ?? '';

        return [
            'code' => $code,
            'label' => (string) ($row['label'] ?? $code),
            'base_url' => rtrim((string) ($row['base_url'] ?? ''), '/'),
            'organization_ref' => (string) ($row['organization_ref'] ?? ''),
            'organization_inn' => $inn,
            'bank_account_number' => (string) ($row['bank_account_number'] ?? ''),
            'service_nomenclature_ref' => (string) ($row['service_nomenclature_ref'] ?? ''),
            'service_nomenclature_code' => (string) ($row['service_nomenclature_code'] ?? ''),
            'currency_ref' => (string) ($row['currency_ref'] ?? ''),
            'date_filter_mode' => $mode,
            'enabled' => (bool) ($row['enabled'] ?? true),
            'include_in_sync' => (bool) ($row['include_in_sync'] ?? true),
        ];
    }

    /**
     * @return Publication
     */
    private function legacyDefault(): array
    {
        return [
            'code' => self::CODE_AUTALLIANCE,
            'label' => 'Автоальянс-Смоленск',
            'base_url' => rtrim((string) config('one_c.base_url', ''), '/'),
            'organization_ref' => (string) config('one_c.organization_ref', ''),
            'organization_inn' => '6732110940',
            'bank_account_number' => (string) config('one_c.bank_statement.account_number', '40702810959710001997'),
            'service_nomenclature_ref' => (string) config('one_c.service_nomenclature.ref', ''),
            'service_nomenclature_code' => (string) config('one_c.service_nomenclature.code', ''),
            'currency_ref' => (string) config('one_c.currency_ref', ''),
            'date_filter_mode' => 'odata',
            'enabled' => true,
            'include_in_sync' => true,
        ];
    }
}
