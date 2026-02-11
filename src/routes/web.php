<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::get('/login', [AuthController::class, 'index'])->name('login');
Route::get('/register', [AuthController::class, 'registerView'])->name('register');
Route::get('/admin/login', [AdminController::class, 'index'])->name('admin-login');
Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/admin/login', [AdminController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'adminLogout'])->name('adminLogout');
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/attendance/list', [AdminController::class, 'list']);
    Route::post('/admin/attendance/list/move', [AdminController::class, 'moveDay']);
    Route::get('/admin/attendance/detail/{id}', [AdminController::class, 'detail']);
    Route::post('/admin/attendance/update', [AdminController::class, 'update'])
        ->name('admin.attendance.update');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminController::class, 'approve']);
    Route::post('/admin/apply', [AdminController::class, 'apply'])
        ->name('admin.apply');
    Route::get('/admin/staff/list', [AdminController::class, 'staffList']);
    Route::get('/admin/attendance/staff/{id}', [AdminController::class, 'staff'])->name('admin.attendance.staff');
    // 前月・翌月ボタン（セッション更新して /attendance/list に戻す）
    Route::post('/admin/attendance/staff/move', [AdminController::class, 'moveMonth'])
        ->name('admin.attendance.staff.move');
    Route::get('/admin/attendance/staff/{id}/csv', [AdminController::class, 'staffCsv'])
        ->name('admin.attendance.staff.csv');
});
Route::middleware('auth')->group(function () {
    Route::get('/verify', function () {
        return redirect()->route('verification.notice'); // => /email/verify
    });
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'detail']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/attendance/regist', [AttendanceController::class, 'store']);
    Route::post('/attendance/rest', [AttendanceController::class, 'rest']);
    Route::post('/attendance/backRest', [AttendanceController::class, 'backRest']);
    Route::post('/attendance/back', [AttendanceController::class, 'back']);
    // 勤怠一覧（表示）※URLは常にここ
    Route::get('/attendance/list', [AttendanceController::class, 'list'])
        ->name('attendance.list');

    // 前月・翌月ボタン（セッション更新して /attendance/list に戻す）
    Route::post('/attendance/list/move', [AttendanceController::class, 'moveMonth'])
        ->name('attendance.list.move');
    Route::post('/attendance/update', [AttendanceController::class, 'update']);
    // 一覧表示（初回表示は apply=1）
    Route::get('/stamp_correction_request/list', [AttendanceController::class, 'stampCorrectionIndex'])
        ->name('stamp_correction');

    // フィルター押下（承認待ち / 承認済み）
    Route::post('/stamp_correction_request/list', [AttendanceController::class, 'stampCorrectionFilter'])
        ->name('stamp_correction.filter');
});
