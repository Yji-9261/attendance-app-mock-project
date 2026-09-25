<?php

namespace App\Http\Controllers\Api;

use App\Models\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;

use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * ログイン処理(トークン作成)
     * POST(/api/v1/login)
     * @param AdminLoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(AdminLoginRequest $request)
    {
        // 認証チェック
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            // 登録情報なしなら401エラー
            return response()->json([
                'error' => 'ログイン情報が登録されていません'
            ], 401);
        }

        // 認証成功時トークン生成し、送信
        return response()->json([
            'message' => '認証に成功しました',
            'token' => $user->createToken('api-token')->plainTextToken,
        ], 200);
    }

    /**
     * ログアウト処理(トークンの削除)
     * GET(/api/v1/logout)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // トークン削除
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'ログアウトしました'
        ], 200);
    }
}
