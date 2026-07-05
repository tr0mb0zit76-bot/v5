<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicTransportRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table unavailable.');
        }
    }

    public function test_public_transport_request_form_is_available_without_auth(): void
    {
        $this->get(route('public.transport-request.create'))
            ->assertOk();
    }

    public function test_public_transport_request_creates_lead(): void
    {
        $marker = 'TRAKLO-REQ-'.uniqid();

        $this->post(route('public.transport-request.store'), [
            'company_name' => 'ООО Тест Логистика',
            'contact_name' => 'Иван Петров',
            'phone' => '+79990001122',
            'email' => 'client@example.test',
            'loading_location' => 'Смоленск',
            'unloading_location' => 'Москва',
            'cargo' => 'Паллеты, 3 тонны',
            'planned_shipping_date' => now()->addDay()->toDateString(),
            'comment' => $marker,
        ])->assertRedirect(route('public.transport-request.create'));

        $lead = Lead::query()
            ->where('source', 'traklo_public_request')
            ->where('description', 'like', '%'.$marker.'%')
            ->latest('id')
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('new', $lead->status);
        $this->assertSame('Смоленск', $lead->loading_location);
        $this->assertSame('Москва', $lead->unloading_location);
        $this->assertSame('Иван Петров', data_get($lead->metadata, 'public_transport_request.contact_name'));
        $this->assertSame('+79990001122', data_get($lead->metadata, 'public_transport_request.phone'));
    }

    public function test_public_transport_request_honeypot_skips_lead_creation(): void
    {
        $marker = 'TRAKLO-SPAM-'.uniqid();

        $this->post(route('public.transport-request.store'), [
            'contact_name' => 'Bot',
            'phone' => '+79990000000',
            'loading_location' => 'A',
            'unloading_location' => 'B',
            'comment' => $marker,
            'website' => 'https://spam.example',
        ])->assertRedirect();

        $this->assertFalse(
            Lead::query()
                ->where('source', 'traklo_public_request')
                ->where('description', 'like', '%'.$marker.'%')
                ->exists()
        );
    }
}
