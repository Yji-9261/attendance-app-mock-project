<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** 会員登録後、登録したメールアドレス宛に認証メールが送信される。 */
    public function test_mail_is_sent_successfully(): void
    {
        Notification::fake();

        $this->get('/register')->assertOk()->assertViewIs('user.register');
        $this->post('/register', [
            'name' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/attendance');

        $user = User::where('email', 'testuser@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());

        Notification::assertSentTo($user, VerifyEmail::class, function ($notification, $channels) use ($user) {
            return in_array('mail', $channels, true)
                && $user->routeNotificationFor('mail', $notification) === 'testuser@example.com';
        });
        Notification::assertCount(1);

        $this->get('/attendance')->assertRedirect(route('verification.notice'));
    }

    /** 認証誘導画面に、メール認証サイトを開くリンクが表示される。 */
    public function test_verification_notice_links_to_mail_site(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSeeText('登録していただいたメールアドレスに認証メールを送付しました。');

        // 外部のMailpit画面への遷移はFeatureテストでは実行せず、リンクを検証する。
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $links = (new \DOMXPath($document))->query('//a[normalize-space(.)="認証はこちらから"]');
        $this->assertSame(1, $links->length);
        $this->assertSame('http://localhost:8025', $links->item(0)->getAttribute('href'));
        $this->assertSame('_blank', $links->item(0)->getAttribute('target'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /** メール内の認証URLで認証を完了すると、勤怠登録画面に遷移する。 */
    public function test_verified_user_is_redirected_to_attendance_page(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $this->get('/attendance')->assertRedirect(route('verification.notice'));

        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VerifyEmail::class);
        $notification = Notification::sent($user, VerifyEmail::class)->first();
        // protectedのverificationUrl()ではなく、メールに実際に設定されたURLを使用する。
        $verificationUrl = $notification->toMail($user)->actionUrl;
        $this->assertNotEmpty($verificationUrl);

        $response = $this->get($verificationUrl);
        // 認証前にアクセスした勤怠画面（intended URL）へ戻る。
        $response->assertRedirect('/attendance');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('user.attendance-register');
    }
}
