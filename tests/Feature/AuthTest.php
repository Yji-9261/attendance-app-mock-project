<?php

namespace Tests\Feature;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ユーザー登録テスト
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected array $data;

    protected function setUp(): void
    {
        parent::setUp();

        // 共通のPOSTデータ
        $this->data = [
            'name' => 'たなかゆうじ',
            'email' => 't@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    /**
     * nameフィールド入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_name_required(): void
    {
        // 1. 名前以外のユーザー情報を入力する
        // 2. 会員登録の処理を行う"
        $this->data['name'] = '';
        $response = $this->post('/register', $this->data);
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * emailフィールド入力必須、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_email_required(): void
    {
        // 1. メールアドレス以外のユーザー情報を入力する
        // 2. 会員登録の処理を行う"
        $this->data['email'] = '';
        $response = $this->post('/register', $this->data);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * passwordフィールド入力最小、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_password_min(): void
    {
        // 1. パスワードを8文字未満にし、ユーザー情報を入力する
        // 2. 会員登録の処理を行う"
        $this->data['password'] = 'passwor';
        $response = $this->post('/register', $this->data);
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    /**
     * passwordフィールド確認一致、バリデーションメッセージテスト
     * @return void
     */
    public function test_user_password_confirmed(): void
    {
        // 1. 確認用のパスワードとパスワードを一致させず、ユーザー情報を入力する
        // 2. 会員登録の処理を行う"
        $this->data['password'] = 'password';
        $this->data['password_confirmation'] = 'passward';
        $response = $this->post('/register', $this->data);
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
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
        // 2. 会員登録の処理を行う"
        $this->data['password'] = '';
        $this->data['password_confirmation'] = '';
        $response = $this->post('/register', $this->data);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
        // 未認証チェック
        $this->assertGuest();
    }

    public function test_user_can_register(): void
    {
        // 1. ユーザー情報を入力する
        // 2. 会員登録の処理を行う"
        $response = $this->post('/register', $this->data);
        $response->assertRedirect('/attendance');
        $this->assertDatabaseHas('users', [
            'name' => 'たなかゆうじ',
            'email' => 't@test.com',
        ]);
        // 認証チェック
        $this->assertAuthenticated();
    }
}
