<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileDocumentUploadTest extends TestCase
{
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
            $roleId = (int) $role->id;
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }

    public function test_mobile_can_upload_order_document_and_receive_chip(): void
    {
        Storage::fake('local');

        $user = $this->createManagerUser();
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('scan-from-phone.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->postJson(route('documents.store'), [
                'order_id' => $order->id,
                'party' => 'customer',
                'type' => 'other',
                'status' => 'sent',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('document.kind', 'document')
            ->assertJsonStructure(['document' => ['id', 'label', 'url']]);

        $this->assertDatabaseHas('order_documents', [
            'order_id' => $order->id,
            'type' => 'other',
            'original_name' => 'scan-from-phone.pdf',
        ]);
    }

    public function test_mobile_rejects_oversized_document_by_page_budget(): void
    {
        Storage::fake('local');
        config([
            'documents.bytes_per_page' => 100,
            'documents.image_placeholder_pages' => 1,
        ]);

        $user = $this->createManagerUser();
        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('oversized-photo.jpg', 50, 'image/jpeg');

        $this->actingAs($user)
            ->postJson(route('documents.store'), [
                'order_id' => $order->id,
                'party' => 'customer',
                'type' => 'other',
                'status' => 'sent',
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}
