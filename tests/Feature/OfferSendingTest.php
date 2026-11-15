<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OfferChannel;
use App\Enums\OfferStatus;
use App\Enums\SettingKey;
use App\Mail\KpMail;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\User;
use App\Services\KpTemplateService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfferSendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['mail.default' => 'smtp', 'mail.from.address' => 'kp@es12.ru']);
        Mail::fake();
    }

    public function test_email_is_sent_personally_and_recorded(): void
    {
        $admin = User::factory()->admin()->create();
        $contract = $this->contract('info@winner.test');

        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/email')
            ->assertRedirect('/tenders/'.$contract->id)
            ->assertSessionHas('status');

        Mail::assertSent(KpMail::class, function (KpMail $mail) use ($contract): bool {
            return $mail->hasTo('info@winner.test')
                && str_contains($mail->offer->subject, $contract->subject)
                && str_contains($mail->offer->body, '«'.$contract->subject.'»')
                && str_ends_with($mail->offer->body, KpTemplateService::UNSUBSCRIBE)
                && $mail->attachmentPath === null;
        });

        $offer = Offer::query()->sole();
        $this->assertSame(OfferStatus::Sent, $offer->status);
        $this->assertSame(OfferChannel::Email, $offer->channel);
        $this->assertSame($admin->id, $offer->user_id);
        $this->assertSame(now()->addDays(3)->toDateString(), $offer->remind_on?->toDateString());
    }

    public function test_pdf_is_attached_when_uploaded(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/template', [
            'subject' => 'Тема {объект}',
            'body' => 'Текст, КП во вложении',
            'daily_limit' => 30,
            'attachment' => UploadedFile::fake()->create('kp.pdf', 100, 'application/pdf'),
        ])->assertRedirect('/template');

        $contract = $this->contract('info@winner.test');
        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/email');

        Mail::assertSent(KpMail::class, fn (KpMail $mail): bool => $mail->attachmentPath !== null
            && $mail->attachmentName === KpTemplateService::ATTACHMENT_NAME);
    }

    public function test_mail_not_configured_gives_clear_message(): void
    {
        config(['mail.default' => 'log']);
        $contract = $this->contract('info@winner.test');

        $this->actingAs(User::factory()->admin()->create())->post('/tenders/'.$contract->id.'/email')
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'ещё не подключена'));

        Mail::assertNothingSent();
        $this->assertSame(0, Offer::query()->count());
    }

    public function test_winner_without_email_is_reported(): void
    {
        $contract = $this->contract(null);

        $this->actingAs(User::factory()->admin()->create())->post('/tenders/'.$contract->id.'/email')
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'нет почты'));

        Mail::assertNothingSent();
    }

    public function test_daily_limit_stops_sending(): void
    {
        $this->app->make(SettingsService::class)->put(SettingKey::KpDailyLimit, '1');
        Offer::factory()->create(['channel' => OfferChannel::Email, 'sent_at' => now()]);
        $contract = $this->contract('info@winner.test');

        $this->actingAs(User::factory()->admin()->create())->post('/tenders/'.$contract->id.'/email')
            ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'дневной лимит'));

        Mail::assertNothingSent();
    }

    public function test_whatsapp_opens_chat_with_letter_and_marks_sent(): void
    {
        $contract = $this->contract('info@winner.test', '8 (937) 112-48-97');

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post('/tenders/'.$contract->id.'/messenger', ['channel' => 'whatsapp']);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/79371124897?text=', $location);
        $this->assertStringContainsString(rawurlencode($contract->subject), $location);
        $this->assertSame(OfferChannel::WhatsApp, Offer::query()->sole()->channel);
    }

    public function test_messenger_without_phone_and_bad_channel(): void
    {
        $admin = User::factory()->admin()->create();
        $contract = $this->contract('info@winner.test', null);

        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/messenger', ['channel' => 'telegram'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/messenger', ['channel' => 'email'])
            ->assertSessionHasErrors('channel');

        $this->assertSame(0, Offer::query()->count());
    }

    public function test_dismiss_hides_contract(): void
    {
        $contract = $this->contract('info@winner.test');

        $this->actingAs(User::factory()->admin()->create())->post('/tenders/'.$contract->id.'/dismiss')
            ->assertRedirect('/');

        $this->assertSame(OfferStatus::Dismissed, Offer::query()->sole()->status);
    }

    public function test_non_admin_cannot_send(): void
    {
        $contract = $this->contract('info@winner.test');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tenders/'.$contract->id.'/email')->assertForbidden();
        $this->actingAs($user)->post('/tenders/'.$contract->id.'/messenger', ['channel' => 'whatsapp'])->assertForbidden();
        $this->actingAs($user)->post('/tenders/'.$contract->id.'/dismiss')->assertForbidden();

        Mail::assertNothingSent();
    }

    private function contract(?string $email, ?string $phone = '+7 900 000-00-00'): Contract
    {
        $company = Company::factory()->create(['email' => $email, 'phone' => $phone]);

        return Contract::factory()->hot()->create(['company_id' => $company->id]);
    }
}
