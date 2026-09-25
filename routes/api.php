<?php

use App\Http\Controllers\Api\AttendanceRecordController;
use App\Http\Controllers\Api\AuthController;

use Illuminate\Http\Request;
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

    // 認証不要(index/show)
    Route::get('attendance-records', [AttendanceRecordController::class, 'index'])
        ->name('attendance-records.index');
    Route::get('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show'])
        ->name('attendance-records.show');

    // 認証必須(logout/store/update/destroy)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('logout', [AuthController::class, 'logout']);
        Route::post('attendance-records', [AttendanceRecordController::class, 'store']);
        Route::put('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::delete('attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
