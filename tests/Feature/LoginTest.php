<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般ユーザーemail
     *
     * @var string
     */
    private const USER_EMAIL = 'user@example.com';

    /**
     * 一般ユーザーpassword
     *
     * @var string
     */
    private const USER_PASSWORD = 'password';

    /**
     * 管理者email
     *
     * @var string
     */
    private const ADMIN_EMAIL = 'admin@example.com';

    /**
     * 管理者パスワード
     *
     * @var string
     */
    private const ADMIN_PASSWORD = 'password';

    /**
     * 存在しないemail
     *
     * @var string
     */
    private const ANONYMOUS_EMAIL = 'guest@example.com';

    /**
     * 存在しないpassword
     *
     * @var string
     */
    private const ANONYMOUS_PASSWORD = 'pass';

    protected function setUp(): void
    {
        parent::setUp();

        // 一般ユーザー登録
        User::factory()->create([
            'name' => 'user',
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
            'admin_status' => false,
        ]);

        // 管理者登録
        User::factory()->create([
            'name' => 'admin',
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
            'admin_status' => true,
        ]);
    }

    /**
     * メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserEmailRequiredValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       メールアドレス以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「メールアドレスを入力してください」というバリデーションメッセージが表示される

        $this->loginUser(email: '')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordRequiredValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       パスワード以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「パスワードを入力してください」というバリデーションメッセージが表示される

        $this->loginUser(password: '')
            ->assertSessionHasErrors([
                'password' => 'パスワードを入力してください',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     *  登録内容と一致しない場合、バリデーションメッセージが表示される
     */
    public function testUserEmailNotRegisterValidation(): void
    {
        // 1. ユーザーを登録する
        // 2. 誤ったメールアドレスのユーザー情報を入力する
        // 3. ログインの処理を行う
        // 「ログイン情報が登録されていません」というバリデーションメッセージが表示される

        $this->loginUser(email: self::ANONYMOUS_EMAIL)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        $this->loginUser(password: self::ANONYMOUS_PASSWORD)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     * ログイン認証機能（管理者） メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function testAdminEmailRequiredValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       メールアドレス以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.	「メールアドレスを入力してください」というバリデーションメッセージが表示される

        $this->loginAdmin(email: '')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function testAdminPasswordRequiredValidation(): void
    {

        // 1.       ユーザーを登録する
        // 2.       パスワード以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「パスワードを入力してください」というバリデーションメッセージが表示される

        $this->loginAdmin(password: '')
            ->assertSessionHasErrors([
                'password' => 'パスワードを入力してください',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない場合、バリデーションメッセージが表示される
     */
    public function testAdminEmailNotRegisterValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       誤ったメールアドレスのユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「ログイン情報が登録されていません」というバリデーションメッセージが表示される

        $this->loginAdmin(email: self::ANONYMOUS_EMAIL)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        $this->loginAdmin(password: self::ANONYMOUS_PASSWORD)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        // 未認証検証(オプション検証)
        $this->assertGuest();
    }

    /**
     * 一般ユーザーログイン
     *
     * @param  mixed  $email
     * @param  mixed  $password
     * @return TestResponse
     */
    private function loginUser(
        $email = self::USER_EMAIL,
        $password = self::USER_PASSWORD
    ) {
        return $this->post('/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * 管理者ログイン
     *
     * @param  mixed  $email
     * @param  mixed  $password
     * @return TestResponse
     */
    private function loginAdmin(
        $email = self::ADMIN_EMAIL,
        $password = self::ADMIN_PASSWORD
    ) {
        return $this->post('/admin/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }
}
