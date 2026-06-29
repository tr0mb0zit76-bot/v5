<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DocumentUploadBudgetEstimateTest extends TestCase
{
    private ?string $tempPdfPath = null;

    protected function tearDown(): void
    {
        if ($this->tempPdfPath !== null && is_file($this->tempPdfPath)) {
            @unlink($this->tempPdfPath);
        }

        parent::tearDown();
    }

    public function test_estimate_upload_budget_returns_two_pages_for_two_page_pdf(): void
    {
        config(['documents.bytes_per_page' => 600 * 1024]);

        $user = $this->userWithDocumentsAccess();
        $this->tempPdfPath = $this->createPdfWithPages(2);

        $response = $this->actingAs($user)->post(route('documents.estimate-upload-budget'), [
            'file' => new UploadedFile($this->tempPdfPath, 'two-pages.pdf', 'application/pdf', null, true),
        ]);

        $response->assertOk();
        $response->assertJsonPath('pages', 2);
        $response->assertJsonPath('within_budget', true);
        $this->assertGreaterThanOrEqual(1_048_576, (int) $response->json('max_bytes'));
    }

    private function userWithDocumentsAccess(): User
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Менеджер',
            'permissions' => [],
            'visibility_areas' => ['dashboard', 'orders', 'documents'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function createPdfWithPages(int $pageCount): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-pdf-est-');
        if ($path === false) {
            $this->fail('Не удалось создать временный PDF.');
        }

        $pdfPath = $path.'.pdf';
        @unlink($path);

        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        for ($i = 0; $i < $pageCount; $i++) {
            $pdf->AddPage();
        }

        $pdf->Output($pdfPath, 'F');

        return $pdfPath;
    }
}
