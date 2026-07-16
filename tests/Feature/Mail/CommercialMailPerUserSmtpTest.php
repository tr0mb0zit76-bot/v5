<?php

namespace Tests\Feature\Mail;

use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommercialMailPerUserSmtpTest extends TestCase
{
    #[Test]
    public function smtp_send_requires_mail_imap_secret(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Почтовые или lead-таблицы недоступны.');
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.hosting.reg.ru',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.scheme' => 'smtps',
        ]);

        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@avtoaliyans.ru',
            'mail_imap_secret' => null,
        ]);
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-SMTP-1',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        $response = $this->actingAs($user)->post(route('leads.offers.send-email', [$lead, $offer]), [
            'to' => ['client@example.com'],
            'subject' => 'Тест SMTP',
            'body' => 'Текст.',
        ]);

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    #[Test]
    public function smtp_send_uses_user_mailbox_password(): void
    {
        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('leads') || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Почтовые или lead-таблицы недоступны.');
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.hosting.reg.ru',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Администратор',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@avtoaliyans.ru',
        ]);
        $user->applyMailImapPassword('reg-ru-mailbox-secret');
        $user->save();

        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $offer = $lead->offers()->create([
            'status' => 'prepared',
            'number' => 'КП-SMTP-2',
            'offer_date' => now()->toDateString(),
            'currency' => 'RUB',
        ]);

        $response = $this->actingAs($user)->post(route('leads.offers.send-email', [$lead, $offer]), [
            'to' => ['client@example.com'],
            'subject' => 'Тест SMTP OK',
            'body' => 'Текст.',
        ]);

        $response->assertRedirect();
        Mail::assertSent(CommercialOutboundMail::class, function (CommercialOutboundMail $mail) use ($user): bool {
            return $mail->fromEmail === strtolower($user->email)
                && $mail->hasTo('client@example.com');
        });

        $this->assertNull(config('mail.mailers.smtp.username'));
        $this->assertNull(config('mail.mailers.smtp.password'));
    }
}
