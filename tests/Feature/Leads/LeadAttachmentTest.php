<?php

namespace Tests\Feature\Leads;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadAttachmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'lead_attachments',
            'lead_offers',
            'lead_activities',
            'lead_cargo_items',
            'lead_route_points',
            'leads',
            'contractors',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('customer');
            $table->string('name');
            $table->string('inn', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new');
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address', 500);
            $table->timestamps();
        });

        Schema::create('lead_cargo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('type', 50);
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('status', 50)->default('draft');
            $table->string('number')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

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
