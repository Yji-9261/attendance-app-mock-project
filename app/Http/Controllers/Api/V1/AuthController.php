<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * ログイン処理(トークン作成)
     * POST(/api/v1/login)
     * 
     * @param LoginRequest $request ログインリクエスト
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // email,passwordによる認証、認証失敗で401エラーとする
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'error' => 'ログイン情報が登録されていません',
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
     * POST(/api/v1/logout)
     * 
     * @param Request $request リクエスト
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // トークンを削除
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'ログアウトしました',
        ], 200);
    }
}
