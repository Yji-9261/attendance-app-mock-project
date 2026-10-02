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

    /** 
     * 会員登録後、登録したメールアドレス宛に認証メールが送信される。 
     */
    public function testMailIsSentSuccessfully(): void
    {
        /**
         * 1. 会員登録をする
         * 2. 認証メールを送信する
         * 登録したメールアドレス宛に認証メールが送信されているか
         */
        // メール実際に送信しない様にする
        Notification::fake();

        // ユーザー登録画面に検証
        $this->get('/register')->assertOk()->assertViewIs('user.register');
        // ユーザー登録しエラーが発生していないことを検証
        $this->post('/register', [
            'name' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/attendance');

        // // 問題なく登録されログイン済みか検証
        $user = User::where('email', 'testuser@example.com')->firstOrFail();

        // 該当ユーザーに認証メールが送られたか検証
        Notification::assertSentTo($user, VerifyEmail::class, function ($notification, $channels) use ($user) {
            return in_array('mail', $channels, true)
                && $user->routeNotificationFor('mail', $notification) === 'testuser@example.com';
        });
        Notification::assertCount(1);

        // メール認証画面にリダイレクトされているか検証
        $this->get('/attendance')->assertRedirect(route('verification.notice'));
    }

    /** 
     * メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する
     */
    public function testVerificationNoticeLinksToMailSite(): void
    {
        // メール認証されていないユーザーを作成
        $user = User::factory()->unverified()->create();

        // メール認証誘導画面表示
        $response = $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSeeText('登録していただいたメールアドレスに認証メールを送付しました。');

        // 外部のMailpit画面への遷移はFeatureテストでは実行せず、リンクを検証する。
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        // リンク先を検証し、メール認証サイト(Mailpit)であることを検証する
        $links = (new \DOMXPath($document))->query('//a[normalize-space(.)="認証はこちらから"]');
        $this->assertSame(1, $links->length);
        $this->assertSame('http://localhost:8025', $links->item(0)->getAttribute('href'));
        $this->assertSame('_blank', $links->item(0)->getAttribute('target'));
    }

    /** 
     * メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移する
     */
    public function testVerifiedUserIsRedirectedToAttendancePage(): void
    {
        // 実際にメールを送信しない様にする
        Notification::fake();

        // メール認証されていないユーザーを作成
        $user = User::factory()->unverified()->create();

        // 勤怠登録画面に遷移せずに、メール認証画面にリダイレクトされることを検証
        $this->actingAs($user);
        $this->get('/attendance')->assertRedirect(route('verification.notice'));

        // 認証メールを送り、実際に送られていることを検証
        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VerifyEmail::class);

        // 遅れた認証通知を取得
        $notification = Notification::sent($user, VerifyEmail::class)->first();

        // メール形式に変換し、認証URLが空でないか検証
        $verificationUrl = $notification->toMail($user)->actionUrl;
        $this->assertNotEmpty($verificationUrl);

        // 認証URLにアクセス
        $response = $this->get($verificationUrl);

        // 認証前にアクセスした勤怠登録画面へリダイレクトされることを検証
        $response->assertRedirect('/attendance');

        // メール認証が完了していることを検証
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 勤怠登録画面に遷移していることを検証
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('user.attendance-register');
    }
}
