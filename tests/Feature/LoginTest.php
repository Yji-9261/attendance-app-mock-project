<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;



class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected array $userPostData;
    protected array $adminPostData;

    protected function setUp(): void
    {
        parent::setUp();

        // 一般ユーザー用POSTデータ
        $this->userPostData = [
            'email' => 'taro@test.com',
            'password' => 'password',
        ];

        // 管理者用POSTデータ
        $this->adminPostData = [
            'email' => 'admin@test.com',
            'password' => 'password',
        ];

        // 一般ユーザー登録
        User::factory()->create([
            'name' => '一般太郎',
            'email' => 'taro@test.com',
            'password' => 'password',
            'admin_status' => false
        ]);

        // 管理者登録
        User::factory()->create([
            'name' => '管理者一郎',
            'email' => 'admin@test.com',
            'password' => 'password',
            'admin_status' => true
        ]);
    }

    /**
     * emailフィールド入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_email_required(): void
    {
        // 1. メールアドレス以外のユーザー情報を入力する
        // 2. ログインの処理を行う
        $this->userPostData['email'] = '';
        $response = $this->post('/login', $this->userPostData);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * passwordフィールド入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_password_required(): void
    {
        // 1. パスワード以外のユーザー情報を入力する
        // 2. ログインの処理を行う
        $this->userPostData['password'] = '';
        $response = $this->post('/login', $this->userPostData);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * 未登録ログイン、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_email_not_register(): void
    {
        // 1. 誤ったユーザー情報を入力する
        // 2. ログインの処理を行う

        // メールアドレス間違い
        $this->userPostData['email'] = 'test';
        $response = $this->post('/login', $this->userPostData);
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * 管理者email入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_admin_email_required(): void
    {
        // 1. 誤ったユーザー情報を入力する
        // 2. ログインの処理を行う

        // メールアドレス間違い
        $this->adminPostData['email'] = '';
        $response = $this->post('/admin/login', $this->adminPostData);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * 管理者password入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_admin_password_required(): void
    {
        // 1. 誤ったユーザー情報を入力する
        // 2. ログインの処理を行う

        // メールアドレス間違い
        $this->adminPostData['password'] = '';
        $response = $this->post('/admin/login', $this->adminPostData);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * 管理者未登録ログイン、バリデーションメッセージテスト
     * @return void
     */
    public function test_admin_email_not_register(): void
    {
        // 1. 誤ったユーザー情報を入力する
        // 2. ログインの処理を行う

        // メールアドレス間違い
        $this->adminPostData['email'] = 'test';
        $response = $this->post('/admin/login', $this->adminPostData);
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }
}
