<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class AdminController extends Controller
{
    /**
     * 管理者ログイン画面
     * GET(/admin/login)
     * 
     * @return View|\Illuminate\Contracts\View\Factory
     */
    public function loginView(): View
    {
        return view('admin.admin-login');
    }

    /**
     * 管理者ログイン処理
     * POST(/admin/login)
     *
     * @param  AdminLoginRequest  $request
     */
    public function login(AdminLoginRequest $request): RedirectResponse|Redirector
    {
        $validated = $request->validated();
        $isAuthorized = auth()->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            // 管理者権限でのログインのみ許可
            'admin_status' => true,
        ]);

        // 認証完了で僭称IDを再生成しhome画面として勤怠一覧画面へリダイレクト
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

     * @param Request $request
     * @return Redirector|RedirectResponse
     */
    public function logout(Request $request): Redirector|RedirectResponse
    {
        // ログアウトしセッション無効化しCSRFトークンを再生成する
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
