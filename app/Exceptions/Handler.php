<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => '勤怠情報が見つかりませんでした。'
                ], 404);
            }
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。'
            ], 403);
        }

        return parent::render($request, $e);
    }
}
