<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;

use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedForGeneralUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $redirectToRoute = null): Response
    {
        // 一般ユーザーで、メール認証を行なっていないならメール認証画面にリダイレクト
        if (
            !$request->user()->admin_status &&
            ($request->user() instanceof MustVerifyEmail &&
                !$request->user()->hasVerifiedEmail())
        ) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice'));
        }

        return $next($request);
    }
}
