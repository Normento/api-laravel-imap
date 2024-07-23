<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailFolderController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\MailMessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);



Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mail-accounts', [MailAccountController::class, 'store']);
    Route::get('/mail-accounts', [MailAccountController::class, 'index']);
    Route::get('/mail-accounts/{id}', [MailAccountController::class, 'show']);
    Route::put('/mail-accounts/{id}', [MailAccountController::class, 'update']);
    Route::delete('/mail-accounts/{id}', [MailAccountController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->group(function () {
    // Routes pour les dossiers mail
    Route::get('/mail-accounts/{id}/folders', [MailFolderController::class, 'index']);
    Route::post('/mail-accounts/{id}/folders', [MailFolderController::class, 'store']);
    Route::delete('/mail-accounts/{id}/folders/{folder_name}', [MailFolderController::class, 'destroy']);

    // Routes pour les messages mail
    Route::get('/mail-accounts/{id}/folders/{folder_name}/messages', [MailMessageController::class, 'index']);
    Route::get('/mail-accounts/{id}/folders/{folder_name}/messages/{message_id}', [MailMessageController::class, 'show']);
    Route::delete('/mail-accounts/{id}/folders/{folder_name}/messages/{message_id}', [MailMessageController::class, 'destroy']);
    Route::post('/mail-accounts/{id}/folders/{folder_name}/messages/{message_id}/move', [MailMessageController::class, 'move']);
    Route::post('/mail-accounts/{id}/folders/{folder_name}/messages/{message_id}/reply', [MailMessageController::class, 'reply']);
    Route::post('/mail-accounts/{id}/messages', [MailMessageController::class, 'send']);
    Route::post('/mail-accounts/{id}/restore/{message_id}', [MailMessageController::class, 'restore']);

    Route::get('mail-accounts/{id}/attachments/{message_uid}/{attachment_id}', [MailMessageController::class, 'downloadAttachment'])->name('downloadAttachment');


});
