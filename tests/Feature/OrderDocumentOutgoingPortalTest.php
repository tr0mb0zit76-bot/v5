<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderPortalInvite;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\OrderPortalInviteService;
use App\Support\OrderDocumentDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderDocumentOutgoingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_lists_and_downloads_outgoing_documents(): void
    {
        if (! Schema::hasTable('order_portal_invites') || ! Schema::hasTable('order_documents')) {
            $this->markTestSkipped('Required tables are not applied.');
        }

        Storage::fake('local');
        config(['document_storage.driver' => DocumentStorageService::DRIVER_LOCAL]);

        [$invite, $token, $order] = $this->createCustomerInvite();

        $path = 'order_documents/'.$order->id.'/invoice-out.pdf';
        Storage::disk('local')->put($path, '%PDF-outgoing-customer');

        $outgoing = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'invoice',
            'status' => 'sent',
            'original_name' => 'invoice-out.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'metadata' => [
                'party' => 'customer',
                'direction' => OrderDocumentDirection::OUTGOING,
                'storage_driver' => DocumentStorageService::DRIVER_LOCAL,
                'flow' => 'uploaded',
            ],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'upd',
            'status' => 'signed',
            'original_name' => 'upd-in.pdf',
            'file_path' => 'order_documents/'.$order->id.'/upd-in.pdf',
            'metadata' => [
                'party' => 'customer',
                'direction' => OrderDocumentDirection::INCOMING,
                'flow' => 'uploaded',
            ],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'invoice',
            'status' => 'sent',
            'original_name' => 'carrier-out.pdf',
            'file_path' => 'order_documents/'.$order->id.'/carrier-out.pdf',
            'metadata' => [
                'party' => 'carrier',
                'direction' => OrderDocumentDirection::OUTGOING,
                'flow' => 'uploaded',
            ],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->get(route('portal.customer.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/CustomerDocuments')
                ->has('outgoing_documents', 1)
                ->where('outgoing_documents.0.id', $outgoing->id)
                ->where('outgoing_documents.0.original_name', 'invoice-out.pdf'));

        $this->get(route('portal.customer.documents.download', [
            'token' => $token,
            'orderDocument' => $outgoing->id,
        ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=invoice-out.pdf');
    }

    public function test_customer_portal_download_rejects_incoming_document(): void
    {
        if (! Schema::hasTable('order_portal_invites') || ! Schema::hasTable('order_documents')) {
            $this->markTestSkipped('Required tables are not applied.');
        }

        Storage::fake('local');
        config(['document_storage.driver' => DocumentStorageService::DRIVER_LOCAL]);

        [$invite, $token, $order] = $this->createCustomerInvite();

        $path = 'order_documents/'.$order->id.'/upd.pdf';
        Storage::disk('local')->put($path, '%PDF-incoming');

        $incoming = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'upd',
            'status' => 'signed',
            'original_name' => 'upd.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'metadata' => [
                'party' => 'customer',
                'direction' => OrderDocumentDirection::INCOMING,
                'storage_driver' => DocumentStorageService::DRIVER_LOCAL,
                'flow' => 'uploaded',
            ],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->get(route('portal.customer.documents.download', [
            'token' => $token,
            'orderDocument' => $incoming->id,
        ]))->assertNotFound();
    }

    public function test_carrier_portal_lists_outgoing_for_carrier_party(): void
    {
        if (! Schema::hasTable('order_portal_invites') || ! Schema::hasTable('order_documents')) {
            $this->markTestSkipped('Required tables are not applied.');
        }

        Storage::fake('local');
        config(['document_storage.driver' => DocumentStorageService::DRIVER_LOCAL]);

        [, , $order, , $token] = $this->createCarrierInvite();

        $path = 'order_documents/'.$order->id.'/carrier-invoice.pdf';
        Storage::disk('local')->put($path, '%PDF-outgoing-carrier');

        $outgoing = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'invoice',
            'status' => 'sent',
            'original_name' => 'carrier-invoice.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'metadata' => [
                'party' => 'carrier',
                'direction' => OrderDocumentDirection::OUTGOING,
                'storage_driver' => DocumentStorageService::DRIVER_LOCAL,
                'flow' => 'uploaded',
            ],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->get(route('portal.carrier.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/CarrierFleet')
                ->has('outgoing_documents', 1)
                ->where('outgoing_documents.0.id', $outgoing->id));

        $this->get(route('portal.carrier.documents.download', [
            'token' => $token,
            'orderDocument' => $outgoing->id,
        ]))->assertOk();
    }

    /**
     * @return array{0: OrderPortalInvite, 1: string, 2: Order}
     */
    private function createCustomerInvite(): array
    {
        $staff = $this->createManagerUser();
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказчик Portal Out',
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-OUT-CUST',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
            'customer_id' => $customer->id,
            'manager_id' => $staff->id,
        ]);

        $result = app(OrderPortalInviteService::class)->createCustomerDocumentsInvite($order, $staff);

        return [$result['invite'], $result['token'], $order];
    }

    /**
     * @return array{0: User, 1: Contractor, 2: Order, 3: OrderPortalInvite, 4: string}
     */
    private function createCarrierInvite(): array
    {
        $user = $this->createManagerUser();
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Перевозчик Portal Out',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-OUT-CARR',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
            'manager_id' => $user->id,
            'carrier_id' => $carrier->id,
        ]);
        $token = 'test-portal-out-'.uniqid('', true);
        $invite = OrderPortalInvite::query()->create([
            'order_id' => $order->id,
            'contractor_id' => $carrier->id,
            'stage' => 'leg_1',
            'carrier_slot' => 1,
            'purpose' => OrderPortalInvite::PURPOSE_CARRIER_FLEET,
            'token_hash' => app(OrderPortalInviteService::class)->hashToken($token),
            'created_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        return [$user, $carrier, $order, $invite, $token];
    }

    private function createManagerUser(): User
    {
        $role = DB::table('roles')->where('name', 'manager')->first();

        if ($role === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode(['orders', 'documents']),
                'visibility_scopes' => json_encode(['orders' => 'own', 'documents' => 'own']),
                'columns_config' => json_encode([]),
                'permissions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = $role->id;
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
