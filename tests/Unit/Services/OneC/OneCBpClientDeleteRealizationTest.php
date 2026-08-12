<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Services\OneC\OneCBpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OneCBpClientDeleteRealizationTest extends TestCase
{
    public function test_fake_deletes_unposted_and_blocks_posted(): void
    {
        config(['one_c.driver' => 'fake']);

        $client = app(OneCBpClient::class);

        $doc = $client->getRealization('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $this->assertNotNull($doc);
        $this->assertFalse($doc['posted']);
        $client->deleteUnpostedRealization('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        $this->expectException(ValidationException::class);
        $client->deleteUnpostedRealization('11111111-1111-1111-1111-111111111111');
    }

    public function test_http_delete_checks_posted_then_deletes(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'secret',
            'one_c.odata.realization_path' => '/odata/standard.odata/Document_РеализацияТоваровУслуг',
        ]);

        $ref = '9b076c72-94d7-11f1-bf0b-00155df5db07';

        Http::fake(function ($request) use ($ref) {
            if ($request->method() === 'PATCH') {
                return Http::response([
                    'Ref_Key' => $ref,
                    'Number' => '0000-000083',
                    'Posted' => false,
                    'DeletionMark' => true,
                ], 200);
            }

            return Http::response([
                'Ref_Key' => $ref,
                'Number' => '0000-000083',
                'Posted' => false,
                'DeletionMark' => false,
            ], 200);
        });

        app(OneCBpClient::class)->deleteUnpostedRealization($ref);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET');
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && ($request['DeletionMark'] ?? false) === true;
        });
    }

    public function test_http_blocks_posted_realization(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'secret',
            'one_c.odata.realization_path' => '/odata/standard.odata/Document_РеализацияТоваровУслуг',
        ]);

        $ref = 'posted-ref-1';

        Http::fake([
            'one-c.test/*' => Http::response([
                'Ref_Key' => $ref,
                'Number' => '0000-000001',
                'Posted' => true,
            ], 200),
        ]);

        $this->expectException(ValidationException::class);
        app(OneCBpClient::class)->deleteUnpostedRealization($ref);
    }
}
