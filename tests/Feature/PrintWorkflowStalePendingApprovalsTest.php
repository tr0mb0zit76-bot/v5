<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use App\Services\OrderPrintDocumentWorkflowService;
use App\Support\OrderDocumentWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintWorkflowStalePendingApprovalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_approval_supersedes_older_pending_same_template(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['manager_id' => $user->id]);

        $old = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'source' => 'print_template',
            'original_name' => 'old.docx',
            'file_path' => 'order_documents/'.$order->id.'/old.docx',
            'status' => 'pending',
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'metadata' => ['flow' => 'print_template_workflow', 'party' => 'customer'],
        ]);

        $fresh = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'source' => 'print_template',
            'original_name' => 'fresh.docx',
            'file_path' => 'order_documents/'.$order->id.'/fresh.docx',
            'status' => 'draft',
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'metadata' => ['flow' => 'print_template_workflow', 'party' => 'customer'],
        ]);

        app(OrderPrintDocumentWorkflowService::class)->requestApproval($fresh, $user);

        $this->assertDatabaseHas('order_documents', [
            'id' => $fresh->id,
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
        ]);
        $this->assertDatabaseHas('order_documents', [
            'id' => $old->id,
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
            'status' => 'draft',
        ]);
    }

    public function test_cleanup_command_supersedes_pending_when_newer_approved_exists(): void
    {
        $order = Order::factory()->create();

        $stale = OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'source' => 'print_template',
            'original_name' => 'stale.docx',
            'file_path' => 'order_documents/'.$order->id.'/stale.docx',
            'status' => 'pending',
            'workflow_status' => OrderDocumentWorkflowStatus::PENDING_APPROVAL,
            'metadata' => ['flow' => 'print_template_workflow', 'party' => 'carrier'],
        ]);

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'request',
            'source' => 'print_template',
            'original_name' => 'ok.docx',
            'file_path' => 'order_documents/'.$order->id.'/ok.docx',
            'status' => 'pending',
            'workflow_status' => OrderDocumentWorkflowStatus::APPROVED,
            'approved_at' => now(),
            'metadata' => ['flow' => 'print_template_workflow', 'party' => 'carrier'],
        ]);

        $this->artisan('print-workflow:cleanup-stale-pending')
            ->assertSuccessful();

        $this->assertDatabaseHas('order_documents', [
            'id' => $stale->id,
            'workflow_status' => OrderDocumentWorkflowStatus::DRAFT,
        ]);
    }
}
