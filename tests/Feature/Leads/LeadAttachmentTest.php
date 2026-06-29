<?php

namespace Tests\Feature\Leads;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadAttachmentTest extends TestCase
{
    public function test_authenticated_user_can_upload_lead_context_attachment(): void
    {
        Storage::fake('public');

        $user = $this->createLeadsUser();
        $contractor = Contractor::query()->create(['type' => 'customer', 'name' => 'ООО Клиент']);
        $lead = Lead::query()->create([
            'number' => 'L-001',
            'title' => 'Тестовый лид',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('packing-list.pdf', 120, 'application/pdf');

        $response = $this->actingAs($user)->post(route('leads.attachments.store', $lead), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseCount('lead_attachments', 1);

        $attachment = LeadAttachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame($lead->id, $attachment->lead_id);
        $this->assertSame('packing-list.pdf', $attachment->original_name);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_authenticated_user_can_download_lead_attachment(): void
    {
        Storage::fake('public');

        $user = $this->createLeadsUser();
        $contractor = Contractor::query()->create(['type' => 'customer', 'name' => 'ООО Клиент']);
        $lead = Lead::query()->create([
            'number' => 'L-002',
            'title' => 'Лид для скачивания',
            'counterparty_id' => $contractor->id,
            'responsible_id' => $user->id,
        ]);

        $path = 'leads/attachments/test.pdf';
        Storage::disk('public')->put($path, 'pdf-content');

        $attachment = LeadAttachment::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
        ]);

        $response = $this->actingAs($user)->get(route('leads.attachments.download', [$lead, $attachment]));

        $response->assertOk();
        $response->assertDownload('invoice.pdf');
    }

    private function createLeadsUser(): User
    {
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'visibility_areas' => json_encode(['leads'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
