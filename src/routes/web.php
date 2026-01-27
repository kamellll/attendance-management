<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
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
Route::post('/register', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth')->group(function () {
    Route::get('/verify', function () {
        return redirect()->route('verification.notice'); // => /email/verify
    });
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/detail/', [AttendanceController::class, 'index']);
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
});
