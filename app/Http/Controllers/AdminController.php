<?php

namespace App\Http\Controllers;

use \App\Http\Requests\AdminLoginRequest;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * 管理者ログイン画面
     * GET(/admin/login)
     */
    public function loginView()
    {
        return view("admin.admin-login");
    }

    /**
     * 管理者ログイン処理
     * POST(/admin/login)
     */
    public function login(AdminLoginRequest $request)
    {
        $validated = $request->validated();
        $isAuthorized = auth()->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            // 管理者権限でのログインのみ許可
            'admin_status' => true,
        ]);

        if ($isAuthorized) {
            $request->session()->regenerate();
            return redirect('/admin/attendance/list');
        } else {
            return back()->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }
    }

    /**
     * 管理者ログアウト
     * POST(/admin/logout)
     */
    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
