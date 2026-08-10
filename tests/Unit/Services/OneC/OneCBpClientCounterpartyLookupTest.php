<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCBpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneCBpClientCounterpartyLookupTest extends TestCase
{
    public function test_finds_counterparty_via_substringof_inn_filter(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'secret',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
        ]);

        Http::fake([
            'one-c.test/*' => Http::response([
                'value' => [
                    [
                        'Ref_Key' => 'ref-other',
                        'ИНН' => '0000000000',
                        'КПП' => '000000000',
                        'Description' => 'OTHER',
                    ],
                    [
                        'Ref_Key' => 'ref-farm',
                        'ИНН' => '2312178145',
                        'КПП' => '231201001',
                        'Description' => 'ФАРМСЕРВИС ООО',
                    ],
                ],
            ], 200),
        ]);

        $ref = app(OneCBpClient::class)->findCounterpartyRef('2312178145', '231201001');

        $this->assertSame('ref-farm', $ref);

        Http::assertSent(function ($request): bool {
            $filter = (string) $request['$filter'];

            return str_contains($request->url(), 'Catalog_')
                && str_contains($filter, "substringof('2312178145',ИНН)")
                && ! str_contains($filter, 'ИНН eq');
        });
    }
}
