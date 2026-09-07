<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserOrAdminController;
use Illuminate\Support\Facades\Route;
;

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
    return view('welcome');
});


Route::middleware('auth')->group(function () {
    Route::get('/attendance', [UserController::class, 'create']);
    Route::post('/attendance', [UserController::class, 'refresh']);
    Route::get('/attendance/list', [UserController::class, 'index']);
    Route::get('/attendance/{id}', [UserOrAdminController::class, 'detail']);
    Route::post('/attendance/{id}', [UserOrAdminController::class, 'store']);
    Route::get('/stamp_correction_request/list', [UserOrAdminController::class, 'showApplications']);
    Route::get('/application/{id}', [UserController::class, 'showApplicationDetail']);
});

// 管理者ログイン画面・処理
Route::get('/admin/login', [AdminController::class, 'loginView']);
Route::post('/admin/login', [AdminController::class, 'login']);

Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/admin/logout', [AdminController::class, 'logout']);
    Route::get('/admin/attendance/list', [AdminController::class, 'index']);
    Route::get('/admin/staff/list', [AdminController::class, 'staffList']);
    Route::get('/admin/attendance/staff/{id}', [AdminController::class, 'staffAttendance']);
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminController::class, 'showApproval']);
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminController::class, 'approve']);
});