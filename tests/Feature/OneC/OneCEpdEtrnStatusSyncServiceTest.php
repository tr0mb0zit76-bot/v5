<?php

declare(strict_types=1);

namespace Tests\Feature\OneC;

use App\Models\Contractor;
use App\Models\OneCEpdRegistryEntry;
use App\Models\Order;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Models\User;
use App\Services\OneC\OneCEpdEtrnStatusSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OneCEpdEtrnStatusSyncServiceTest extends TestCase
{
    public function test_enriches_links_and_acknowledges_etrn_when_title2_received(): void
    {
        if (! Schema::hasTable('one_c_epd_registry_entries')
            || ! Schema::hasTable('order_document_edo_acknowledgements')
            || ! Schema::hasColumn('one_c_epd_registry_entries', 'document_meta')) {
            $this->markTestSkipped('Нужны таблицы ЭПД registry + ЭДО + колонка document_meta.');
        }

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'http',
            'one_c.username' => 'u',
            'one_c.password' => 'p',
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'Test AA',
                    'base_url' => 'https://1c.test/autalliance',
                    'enabled' => true,
                    'organization_inn' => '6732110940',
                ],
            ],
            'one_c.odata.etrn_path' => '/odata/standard.odata/Document_ЭлектроннаяТранспортнаяНакладная',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
            'one_c.odata.organization_path' => '/odata/standard.odata/Catalog_Организации',
            'one_c.epd_registry.etrn_enrich_top' => 10,
        ]);

        $customer = Contractor::query()->create([
            'name' => 'НОВАФАРМ',
            'inn' => '5835139805',
            'type' => 'customer',
        ]);
        $carrier = Contractor::query()->create([
            'name' => 'МИРАРИТА',
            'inn' => '5245031478',
            'type' => 'carrier',
        ]);
        $order = Order::factory()->create([
            'order_number' => 'АС-ТД-968',
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
        ]);

        $documentRef = (string) Str::uuid();
        $customerRef = (string) Str::uuid();
        $carrierRef = (string) Str::uuid();

        $entry = OneCEpdRegistryEntry::query()->create([
            'publication_code' => 'autalliance',
            'document_ref' => $documentRef,
            'document_type' => OneCEpdRegistryEntry::TYPE_ETRN,
            'document_type_label' => 'ЭТрН',
            'epd_number' => '00000000005',
            'epd_date' => '2026-09-07',
            'carrier_inn' => '5245031478',
            'carrier_name' => 'МИРАРИТА',
            'deletion_mark' => false,
            'posted' => true,
            'last_synced_at' => now(),
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($documentRef, $customerRef, $carrierRef) {
            $url = urldecode($request->url());

            if (str_contains($url, "Document_ЭлектроннаяТранспортнаяНакладная(guid'{$documentRef}')")
                || str_contains($url, $documentRef) && str_contains($url, 'ЭлектроннаяТранспортнаяНакладная')) {
                return Http::response([
                    'Ref_Key' => $documentRef,
                    'ТекущийПолученныйТитул' => 'ЭТрН_Титул2',
                    'ТекущийТитул' => 'ЭТрН_Титул2',
                    'ТекущийШаг' => 'Погрузка',
                    'ТекущийШагВыполнен' => true,
                    'ТитулГрузоотправителяТранспортнаяНакладнаяНомер' => '1',
                    'ТитулГрузоотправителяТранспортнаяНакладнаяДата' => '2026-09-07T00:00:00',
                    'СсылкаТитулГрузоотправителяЗаказчик' => $customerRef,
                    'СсылкаТитулГрузоотправителяПеревозчик' => $carrierRef,
                    'ТитулПеревозчикаПриемкаИдентификаторФайла' => 'ON_TRNACLPPRIN_X',
                ], 200);
            }

            if (str_contains($url, $customerRef)) {
                return Http::response([
                    'Description' => 'НОВАФАРМ',
                    'ИНН' => '5835139805',
                ], 200);
            }

            if (str_contains($url, $carrierRef)) {
                return Http::response([
                    'Description' => 'МИРАРИТА',
                    'ИНН' => '5245031478',
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$url], 404);
        });

        $stats = app(OneCEpdEtrnStatusSyncService::class)->sync('autalliance');

        $this->assertSame(1, $stats['checked'], json_encode($stats, JSON_UNESCAPED_UNICODE));
        $this->assertGreaterThanOrEqual(1, $stats['acknowledged'] + $stats['linked'], json_encode($stats, JSON_UNESCAPED_UNICODE));

        $entry->refresh();
        $this->assertSame($order->id, (int) $entry->order_id);
        $this->assertTrue((bool) data_get($entry->document_meta, 'exchange_with_carrier'));

        $this->assertDatabaseHas('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
            'party' => 'carrier',
            'document_type' => 'etrn',
            'slot_key' => 'etrn',
            'document_number' => '00000000005',
            'received_via_edo' => true,
        ]);
    }

    public function test_does_not_overwrite_manual_etrn_acknowledgement(): void
    {
        if (! Schema::hasTable('one_c_epd_registry_entries')
            || ! Schema::hasTable('order_document_edo_acknowledgements')
            || ! Schema::hasColumn('one_c_epd_registry_entries', 'document_meta')) {
            $this->markTestSkipped('Нужны таблицы ЭПД registry + ЭДО + колонка document_meta.');
        }

        config([
            'one_c.enabled' => true,
            'one_c.driver' => 'http',
            'one_c.username' => 'u',
            'one_c.password' => 'p',
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'Test AA',
                    'base_url' => 'https://1c.test/autalliance',
                    'enabled' => true,
                    'organization_inn' => '6732110940',
                ],
            ],
            'one_c.odata.etrn_path' => '/odata/standard.odata/Document_ЭлектроннаяТранспортнаяНакладная',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
            'one_c.epd_registry.etrn_enrich_top' => 10,
        ]);

        $user = User::factory()->create();
        $customer = Contractor::query()->create(['name' => 'НОВАФАРМ', 'inn' => '5835139805', 'type' => 'customer']);
        $carrier = Contractor::query()->create(['name' => 'МИРАРИТА', 'inn' => '5245031478', 'type' => 'carrier']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
        ]);

        $documentRef = (string) Str::uuid();
        $entry = OneCEpdRegistryEntry::query()->create([
            'publication_code' => 'autalliance',
            'document_ref' => $documentRef,
            'document_type' => OneCEpdRegistryEntry::TYPE_ETRN,
            'epd_number' => '00000000005',
            'order_id' => $order->id,
            'linked_at' => now(),
            'deletion_mark' => false,
            'last_synced_at' => now(),
        ]);

        OrderDocumentEdoAcknowledgement::query()->create([
            'order_id' => $order->id,
            'party' => 'carrier',
            'document_type' => 'etrn',
            'slot_key' => 'etrn',
            'contractor_id' => 0,
            'received_via_edo' => true,
            'document_number' => 'MANUAL-ETRN',
            'document_date' => '2026-09-01',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($documentRef) {
            $url = urldecode($request->url());
            if (str_contains($url, $documentRef)) {
                return Http::response([
                    'ТекущийПолученныйТитул' => 'ЭТрН_Титул2',
                    'ТекущийШаг' => 'Погрузка',
                    'ТекущийШагВыполнен' => true,
                    'СсылкаТитулГрузоотправителяЗаказчик' => (string) Str::uuid(),
                    'СсылкаТитулГрузоотправителяПеревозчик' => (string) Str::uuid(),
                ], 200);
            }

            return Http::response([
                'Description' => 'X',
                'ИНН' => '0000000000',
            ], 200);
        });

        $stats = app(OneCEpdEtrnStatusSyncService::class)->sync('autalliance');

        $this->assertSame(1, $stats['skipped_manual'], json_encode($stats, JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseHas('order_document_edo_acknowledgements', [
            'order_id' => $order->id,
            'document_number' => 'MANUAL-ETRN',
            'confirmed_by' => $user->id,
        ]);
        $this->assertSame($order->id, (int) $entry->fresh()->order_id);
    }
}
