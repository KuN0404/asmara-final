<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\MyAgendaController;
use App\Http\Controllers\API\WhatsAppController;
use App\Http\Controllers\API\ParticipantController;
use App\Http\Controllers\API\AnnouncementController;
use App\Http\Controllers\API\OfficeAgendaController;

// ========================================
// PUBLIC ROUTES (No Auth Required)
// ========================================
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1'); // Max 10 login attempts per minute

// ========================================
// PROTECTED ROUTES (Auth Required)
// ========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // --- AUTH ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- PROFILE ---
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);

    // --- USERS (Super Admin, Kepala, Ketua Tim, Kasubbag) ---
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{id}/restore', [UserController::class, 'restore']);
    });

    // --- ROOMS ---
    Route::get('rooms', [RoomController::class, 'index']);
    Route::get('rooms/{id}', [RoomController::class, 'show']);
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::post('rooms', [RoomController::class, 'store']);
        Route::put('rooms/{id}', [RoomController::class, 'update']);
        Route::delete('rooms/{id}', [RoomController::class, 'destroy']);
    });

    // --- PARTICIPANTS ---
    Route::get('participants', [ParticipantController::class, 'index']);
    Route::get('participants/{id}', [ParticipantController::class, 'show']);
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::post('participants', [ParticipantController::class, 'store']);
        Route::put('participants/{id}', [ParticipantController::class, 'update']);
        Route::delete('participants/{id}', [ParticipantController::class, 'destroy']);
    });

    // --- OFFICE AGENDAS ---
    Route::apiResource('office-agendas', OfficeAgendaController::class);
    
    // Approval routes - hanya kepala dan super_admin
    Route::middleware('role:super_admin,kepala')->group(function () {
        Route::post('office-agendas/{id}/approve', [OfficeAgendaController::class, 'approve']);
        Route::post('office-agendas/{id}/reject', [OfficeAgendaController::class, 'reject']);
    });
    
    // Delete attachment - admin roles only
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::delete('office-agendas/{officeAgenda}/attachments', [OfficeAgendaController::class, 'deleteAttachment']);
        Route::post('office-agendas/{id}/reminder', [OfficeAgendaController::class, 'sendReminder'])->middleware('throttle:1,1'); // Max 1 reminder per minute
    });

    // --- MY AGENDAS ---
    Route::apiResource('my-agendas', MyAgendaController::class);
    Route::post('my-agendas/{id}/restore', [MyAgendaController::class, 'restore']);
    Route::get('public-agendas', [MyAgendaController::class, 'publicAgendas']);

    // --- ANNOUNCEMENTS ---
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::get('announcements/{id}', [AnnouncementController::class, 'show']);
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::post('announcements', [AnnouncementController::class, 'store']);
        Route::put('announcements/{id}', [AnnouncementController::class, 'update']);
        Route::delete('announcements/{id}', [AnnouncementController::class, 'destroy']);
    });

    // --- WHATSAPP (Admin only) ---
    Route::middleware('role:super_admin,kepala,ketua_tim,kasubbag')->group(function () {
        Route::get('whatsapp/status', [WhatsAppController::class, 'status']);
        Route::get('whatsapp/logs', [WhatsAppController::class, 'logs']);
        Route::post('whatsapp/send-test', [WhatsAppController::class, 'sendTest'])->middleware('throttle:5,1'); // Limit test messages
    });
});