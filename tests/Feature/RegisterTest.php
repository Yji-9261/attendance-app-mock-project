<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 新規登録NAME
     *
     * @var string
     */
    protected const NAME = 'user';

    /**
     * 新規登録EMAIL
     *
     * @var string
     */
    protected const EMAIL = 'user@example.com';

    /**
     * 新規登録パスワード
     *
     * @var string
     */
    protected const PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * 名前が未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserNameRequired(): void
    {
        // 1.       名前以外のユーザー情報を入力する
        // 2.       会員登録の処理を行う
        // result.  「お名前を入力してください」というバリデーションメッセージが表示される
        $this->registerUser(name: '')
            ->assertSessionHasErrors([
                'name' => 'お名前を入力してください',
            ]);

        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserEmailRequired(): void
    {
        // 1. メールアドレス以外のユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「メールアドレスを入力してください」というバリデーションメッセージが表示される

        $this->registerUser(email: '')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
            ]);

        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * パスワードが8文字未満の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordMin(): void
    {
        // 1. パスワードを8文字未満にし、ユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードは8文字以上で入力してください」というバリデーションメッセージが表示される

        $this->registerUser(
            password: 'pass'
        )
            ->assertSessionHasErrors([
                'password' => 'パスワードは8文字以上で入力してください',
            ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * パスワードが一致しない場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordConfirmed(): void
    {
        // 1. 確認用のパスワードとパスワードを一致させず、ユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードと一致しません」というバリデーションメッセージが表示される

        $this->registerUser(
            password: 'password',
            password_confirmation: 'passward'
        )
            ->assertSessionHasErrors([
                'password' => 'パスワードと一致しません',
            ]);

        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordRequired(): void
    {
        // 1. パスワード以外のユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードを入力してください」というバリデーションメッセージが表示される

        $this->registerUser(
            password: '',
        )
            ->assertSessionHasErrors([
                'password' => 'パスワードを入力してください',
            ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * フォームに内容が入力されていた場合、データが正常に保存される
     */
    public function testUserCanRegister(): void
    {
        // 1. ユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // データベースに登録したユーザー情報が保存される

        $this->registerUser()->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => self::NAME,
            'email' => self::EMAIL,
        ]);

        // 認証チェック
        $this->assertAuthenticated();
    }

    /**
     * 新規登録処理
     *
     * @param  mixed  $name
     * @param  mixed  $email
     * @param  mixed  $password
     * @param  mixed  $password_confirmation
     * @return TestResponse
     */
    private function registerUser(
        $name = self::NAME,
        $email = self::EMAIL,
        $password = self::PASSWORD,
        $password_confirmation = null,
    ) {
        return $this->post('/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password_confirmation ?? $password,
        ]);
    }
}
