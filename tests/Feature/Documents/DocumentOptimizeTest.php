<?php

namespace Tests\Feature\Documents;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentOptimizeTest extends TestCase
{
    private function userWithDocumentsAccess(): User
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => ['dashboard', 'orders'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_optimize_pdf_returns_payload_when_sidecar_responds(): void
    {
        config([
            'document_ocr.url' => 'http://127.0.0.1:3001',
            'document_ocr.optimize_enabled' => true,
        ]);

        $optimized = base64_encode('%PDF-1.4 optimized');

        Http::fake(function (Request $request) use ($optimized) {
            if (str_contains($request->url(), '/optimize')) {
                return Http::response([
                    'pdf_base64' => $optimized,
                    'original_bytes' => 5_000_000,
                    'optimized_bytes' => 800_000,
                    'method' => 'ocrmypdf',
                    'warnings' => [],
                ], 200);
            }

            return Http::response([], 404);
        });

        $user = $this->userWithDocumentsAccess();

        $response = $this->actingAs($user)->post(route('documents.optimize-pdf'), [
            'file' => UploadedFile::fake()->createWithContent(
                'scan.pdf',
                '%PDF-1.4'.str_repeat("\n", 100),
                'application/pdf',
            ),
        ]);

        $response->assertOk();
        $response->assertJsonPath('method', 'ocrmypdf');
        $response->assertJsonPath('optimized_bytes', 800_000);
        $response->assertJsonPath('within_budget', true);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/optimize');
        });
    }

    public function test_optimize_pdf_returns_service_unavailable_when_disabled(): void
    {
        config([
            'document_ocr.url' => '',
            'document_ocr.optimize_enabled' => false,
        ]);

        $user = $this->userWithDocumentsAccess();

        $response = $this->actingAs($user)->post(route('documents.optimize-pdf'), [
            'file' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(503);
    }
}
