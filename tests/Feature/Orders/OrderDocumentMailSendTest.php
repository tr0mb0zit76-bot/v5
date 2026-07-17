<?php

namespace Tests\Feature\Orders;

use App\Mail\CommercialOutboundMail;
use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Role;
use App\Models\User;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDocumentMailSendTest extends TestCase
{
    #[Test]
    public function send_signed_pdf_by_email_to_counterparty_contact(): void
    {
        if (
            ! Schema::hasTable('orders')
            || ! Schema::hasTable('order_documents')
            || ! Schema::hasTable('mail_threads')
            || ! Schema::hasTable('contractor_contacts')
        ) {
            $this->markTestSkipped('Таблицы заказов/почты недоступны.');
        }

        Mail::fake();
        Storage::fake('local');

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['orders', 'mail'],
                'visibility_scopes' => ['orders' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@avtoaliyans.ru',
            'mail_imap_secret' => 'secret',
        ]);

        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
            'is_active' => true,
        ]);

        ContractorContact::query()->create([
            'contractor_id' => $customer->id,
            'full_name' => 'Анна',
            'email' => 'anna@customer.test',
            'is_primary' => true,
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-MAIL-PDF',
            'company_code' => 'TST',
            'manager_id' => $user->id,
            'order_date' => now()->toDateString(),
            'status' => 'new',
            'customer_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdfPath = 'order_documents/'.$orderId.'/final.pdf';
        Storage::disk('local')->put($pdfPath, '%PDF-1.4 test');

        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'document_group' => null,
            'source' => 'print_template',
            'number' => null,
            'document_date' => null,
            'original_name' => 'request.docx',
            'file_path' => 'order_documents/'.$orderId.'/draft.docx',
            'generated_pdf_path' => $pdfPath,
            'template_id' => null,
            'status' => 'signed',
            'workflow_status' => OrderDocumentWorkflowStatus::APPROVED,
            'signature_status' => 'signed_internal',
            'metadata' => json_encode([
                'flow' => 'print_template_workflow',
                'party' => 'customer',
                'generated_pdf_storage_driver' => 'local',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('order_documents/'.$orderId.'/draft.docx', 'docx');

        $response = $this->actingAs($user)->post(route('orders.documents.send-email', [$orderId, $documentId]), [
            'to' => ['anna@customer.test'],
            'subject' => 'Заявка',
        ]);

        $response->assertRedirect();

        Mail::assertSent(CommercialOutboundMail::class, function (CommercialOutboundMail $mail): bool {
            return $mail->hasTo('anna@customer.test')
                && $mail->outboundAttachments !== [];
        });
    }
}
