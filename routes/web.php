<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StaffController;

use Illuminate\Support\Facades\Route;

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
        // リダイレクトによりHOMEに設定した画面に遷移
        return redirect('login');
    }
});

//一般ユーザー・管理者共通機能
Route::middleware('auth')->group(function () {
    Route::get('/attendance/list', [AttendanceController::class, 'index']);//勤怠一覧表示
    Route::get('/attendance/{id}', [AttendanceController::class, 'show']);//勤怠詳細表示
    Route::post('/attendance/{id}', [ApplicationController::class, 'store']);//修正申請処理
    Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index']);//申請一覧表示
    Route::get('/application/{id}', [ApplicationController::class, 'show']);////申請詳細表示
});

// 一般ユーザーのみの機能
Route::middleware(['auth', 'general'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'create']);//勤怠打刻画 面表示
    Route::post('/attendance', [AttendanceController::class, 'store']);//勤怠打刻処理
});

// 管理者ログイン画面・処理
Route::get('/admin/login', [AdminController::class, 'loginView']);//管理者ログイン画面表示
Route::post('/admin/login', [AdminController::class, 'login']);//管理者ログイン処理

//管理者のみの機能
Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/admin/logout', [AdminController::class, 'logout']);//管理者ログアウト処理
    Route::get('/admin/staff/list', [StaffController::class, 'index']);//スタッフ一覧表示
    Route::get('/admin/attendance/list', [StaffController::class, 'indexAttendance']);//スタッフ勤怠一覧表示
    Route::get('/admin/attendance/staff/{id}', [StaffController::class, 'showAttendance']);//スタッフ勤怠詳細表示
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApprovalController::class, 'show']);//申請承認画面表示
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApprovalController::class, 'store']);//申請承認処理
});