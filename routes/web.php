<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use App\Models\Attendance;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // 非認証なら一般ユーザーのログイン画面へ
    if (Auth::guest()) {
        return redirect('/login');
    }

    // 一般ユーザーと管理者でリダイレクト先を変える
    if (Auth::user()->admin_status) {
        return redirect('/admin/attendance/list');
    } else {
        // 認証済みのためリダイレクトによりHOMEに設定した画面に遷移
        return redirect('login');
    }
});

/** 一般ユーザーのみの機能 */
Route::middleware(['auth', 'verified', 'general'])->group(function () {
    // 勤怠打刻画面表示    
    Route::get('/attendance', [AttendanceController::class, 'create']);
    // 勤怠打刻処理
    Route::post('/attendance', [AttendanceController::class, 'store']);
    // report処理
    Route::get('/attendance/report', [AttendanceController::class, 'report']);
});

/** 管理者ログイン画面・処理 */
// 管理者ログイン画面表示
Route::get('/admin/login', [AdminController::class, 'loginView']);
// 管理者ログイン処理
Route::post('/admin/login', [AdminController::class, 'login']);

/** 管理者のみの機能 */
Route::middleware(['auth', 'admin'])->group(function () {
    // 管理者ログアウト処理
    Route::post('/admin/logout', [AdminController::class, 'logout']);
    // スタッフ一覧表示
    Route::get('/admin/staff/list', [StaffController::class, 'index']);
    // スタッフ勤怠一覧表示
    Route::get('/admin/attendance/list', [StaffController::class, 'indexAttendance']);
    // スタッフ勤怠詳細表示
    Route::get('/admin/attendance/staff/{user}', [StaffController::class, 'showAttendance']);
    // 申請承認画面表示
    Route::get('/stamp_correction_request/approve/{application}', [ApprovalController::class, 'show']);
    // 申請承認処理
    Route::post('/stamp_correction_request/approve/{application}', [ApprovalController::class, 'store']);
    // CSV出力機能
    Route::post('/export', [StaffController::class, 'exportCsv']);
    // bladeファイル上にリンクはないが要件シート上にはあるので用意しておく
    // get('/attendance/{attendance}')と同じ
    Route::get('admin/attendance/{attendance}', [AttendanceController::class, 'show']);
});

/** 一般ユーザー・管理者共通機能 */
Route::middleware(['auth', 'general.verified'])->group(function () {
    // 勤怠一覧表示
    Route::get('/attendance/list', [AttendanceController::class, 'index']);
    // 勤怠詳細表示
    Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);
    // 修正申請処理
    Route::post('/attendance/{attendance}', [ApplicationController::class, 'store']);
    // 申請一覧表示
    Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index']);
    // 申請詳細表示
    Route::get('/application/{application}', [ApplicationController::class, 'show']);
});
