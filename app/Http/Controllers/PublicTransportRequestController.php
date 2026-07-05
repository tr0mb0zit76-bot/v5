<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Support\LeadStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PublicTransportRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/TransportRequest', [
            'submitted' => session('transport_request_submitted', false),
            'traklo_apk_url' => config('external_users.apk_url', '/downloads/traklo.apk'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('leads'), 404);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'loading_location' => ['required', 'string', 'max:255'],
            'unloading_location' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:1000'],
            'planned_shipping_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($validated['website'] ?? null)) {
            return back()->with('transport_request_submitted', true);
        }

        Lead::query()->create([
            'number' => $this->nextLeadNumber(),
            'status' => LeadStatus::values()[0],
            'source' => 'traklo_public_request',
            'title' => $this->title($validated),
            'description' => $this->description($validated),
            'loading_location' => $validated['loading_location'],
            'unloading_location' => $validated['unloading_location'],
            'planned_shipping_date' => $validated['planned_shipping_date'] ?? null,
            'lead_qualification' => [
                'need' => 'Публичная заявка Traklo',
                'timeline' => filled($validated['planned_shipping_date'] ?? null)
                    ? (string) $validated['planned_shipping_date']
                    : null,
            ],
            'metadata' => [
                'public_transport_request' => [
                    'company_name' => $validated['company_name'] ?? null,
                    'contact_name' => $validated['contact_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'cargo' => $validated['cargo'] ?? null,
                    'comment' => $validated['comment'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        return to_route('public.transport-request.create')
            ->with('transport_request_submitted', true);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function title(array $validated): string
    {
        return sprintf(
            'Заявка на перевозку: %s → %s',
            $validated['loading_location'],
            $validated['unloading_location'],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function description(array $validated): string
    {
        $lines = [
            'Публичная заявка Traklo.',
            'Компания: '.($validated['company_name'] ?? 'не указана'),
            'Контакт: '.$validated['contact_name'],
            'Телефон: '.$validated['phone'],
            'Email: '.($validated['email'] ?? 'не указан'),
            'Маршрут: '.$validated['loading_location'].' → '.$validated['unloading_location'],
            'Груз: '.($validated['cargo'] ?? 'не указан'),
            'Дата погрузки: '.($validated['planned_shipping_date'] ?? 'не указана'),
            'Комментарий: '.($validated['comment'] ?? 'нет'),
        ];

        return implode("\n", $lines);
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = DB::table('leads')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
