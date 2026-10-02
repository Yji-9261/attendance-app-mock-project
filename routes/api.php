<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // 認証(トークン作成)
    Route::post('login', [AuthController::class, 'login']);

    // 認証不要
    Route::get('attendance-records', [AttendanceRecordController::class, 'index'])
        ->name('attendance-records.index');
    Route::get('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show'])
        ->name('attendance-records.show');

    // 認証必須
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('attendance-records', [AttendanceRecordController::class, 'store']);
        Route::put('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::delete('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
