<?php

namespace Tests\Feature;

use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadOfferHtmlTeaserMailSendTest extends TestCase
{
    #[Test]
    public function send_offer_embeds_html_teaser_with_cid_and_keeps_pdf_attachment(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Почтовые или lead-таблицы недоступны.');
        }

        Mail::fake();
        Storage::fake('local');

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);

        $relative = 'assets/proposal-emails/teaser-send/dot.png';
        $absolute = public_path($relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }
        file_put_contents($absolute, hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        $pdfPath = 'generated-documents/proposals/teaser.pdf';
        Storage::disk('local')->put($pdfPath, '%PDF-1.4 teaser');

        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-TEASER',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
            'generated_file_path' => $pdfPath,
            'payload' => [
                'source' => 'html_template',
                'content_type' => 'application/pdf',
                'generated_disk' => 'local',
                'has_html_teaser' => true,
                'rendered_html' => '<html><body><p>Затравка</p><img src="/'.$relative.'"></body></html>',
                'email_assets' => [[
                    'cid' => 'dot.png',
                    'public_path' => '/'.$relative,
                    'relative_path' => $relative,
                    'mime' => 'image/png',
                    'filename' => 'dot.png',
                ]],
            ],
        ]);

        $response = $this->actingAs($user)->post(route('leads.offers.send-email', [$lead, $offer]), [
            'to' => ['client@example.com'],
            'subject' => 'Тест затравки',
            'body' => 'Текст запасной.',
            'use_html_teaser' => true,
        ]);

        $response->assertRedirect();

        Mail::assertSent(CommercialOutboundMail::class, function (CommercialOutboundMail $mail) use ($absolute): bool {
            return is_string($mail->bodyHtml)
                && str_contains($mail->bodyHtml, 'cid:dot.png')
                && count($mail->inlineImages) === 1
                && $mail->inlineImages[0]['path'] === $absolute
                && count($mail->outboundAttachments) === 1
                && $mail->outboundAttachments[0]['name'] === 'teaser.pdf';
        });

        @unlink($absolute);
        @rmdir(dirname($absolute));
    }
}
