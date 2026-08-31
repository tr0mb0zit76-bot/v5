<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCBpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneCBpClientCounterpartyPhoneTest extends TestCase
{
    public function test_ensure_counterparty_phone_patches_when_missing(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'secret',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
            'one_c.odata.contact_info_kinds_path' => '/odata/standard.odata/Catalog_ВидыКонтактнойИнформации',
            'one_c.odata.counterparty_phone_kind_ref' => 'kind-phone',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, 'Catalog_')) {
                return Http::response([
                    'Ref_Key' => 'ref-1',
                    'КонтактнаяИнформация' => [],
                ], 200);
            }

            if ($request->method() === 'PATCH') {
                return Http::response([
                    'Ref_Key' => 'ref-1',
                    'КонтактнаяИнформация' => [
                        ['Тип' => 'Телефон', 'НомерТелефона' => '+79001112233'],
                    ],
                ], 200);
            }

            return Http::response(['value' => []], 200);
        });

        app(OneCBpClient::class)->ensureCounterpartyPhone('ref-1', '+79001112233');

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            $ci = $request['КонтактнаяИнформация'] ?? null;

            return is_array($ci)
                && ($ci[0]['Тип'] ?? null) === 'Телефон'
                && ($ci[0]['НомерТелефона'] ?? null) === '+79001112233'
                && ($ci[0]['Вид_Key'] ?? null) === 'kind-phone';
        });
    }

    public function test_ensure_counterparty_phone_skips_when_already_present(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'secret',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
            'one_c.odata.counterparty_phone_kind_ref' => 'kind-phone',
        ]);

        Http::fake([
            'one-c.test/*' => Http::response([
                'Ref_Key' => 'ref-1',
                'КонтактнаяИнформация' => [
                    [
                        'Тип' => 'Телефон',
                        'НомерТелефона' => '+79000000000',
                    ],
                ],
            ], 200),
        ]);

        app(OneCBpClient::class)->ensureCounterpartyPhone('ref-1', '+79001112233');

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => $request->method() === 'PATCH');
    }
}
