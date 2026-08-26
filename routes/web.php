<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstructorAttendanceController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MemberAttendanceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SlotController;
use Illuminate\Support\Facades\Route;

// --- Invitado ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'attempt'])->name('login.attempt');
});

// --- Autenticado ---
// TODO lo que requiere sesión vive DENTRO de este grupo.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::redirect('/', '/horario');

    // =====================================================================
    // HORARIO (T2)
    // =====================================================================
    // Ver horario: todos los roles autenticados (una sola definición).
    Route::get('/horario', [ScheduleController::class, 'index'])->name('schedule.index');

    // Edición del horario: recepción, coordinación, admin.
    Route::middleware('role:admin,coordinator,receptionist')->group(function () {
        Route::get('/horario/plantilla', [ScheduleController::class, 'template'])->name('schedule.template');

        Route::post('/horario/slots', [SlotController::class, 'store'])->name('schedule.slots.store');
        Route::put('/horario/slots/{slot}', [SlotController::class, 'update'])->name('schedule.slots.update');
        Route::delete('/horario/slots/{slot}', [SlotController::class, 'destroy'])->name('schedule.slots.destroy');

        Route::get('/horario/slots/{slot}/roster', [SlotController::class, 'roster'])->name('schedule.slots.roster');
        Route::put('/horario/slots/{slot}/roster', [SlotController::class, 'updateRoster'])->name('schedule.slots.roster.update');

        Route::post('/horario/sesiones/{session}/mover', [SessionController::class, 'move'])->name('schedule.sessions.move');
        Route::post('/horario/sesiones/{session}/cancelar', [SessionController::class, 'cancel'])->name('schedule.sessions.cancel');
        Route::post('/horario/sesiones/{session}/conflictos', [SessionController::class, 'checkConflicts'])->name('schedule.sessions.conflicts');
    });

    // =====================================================================
    // SOCIOS (T1)
    // =====================================================================
    // Importar: solo admin (Gate import-members). ANTES del resource para que
    // /socios/importar no choque con /socios/{member}.
    Route::get('/socios/importar', [MemberImportController::class, 'show'])->name('members.import.show');
    Route::post('/socios/importar', [MemberImportController::class, 'store'])->name('members.import.store');

    Route::resource('socios', MemberController::class)
        ->parameters(['socios' => 'member'])
        ->names('members')
        ->middleware('role:admin,coordinator,receptionist');

    // =====================================================================
    // ASISTENCIA (T3)   <-- ESTO FALTABA
    // =====================================================================
    // Alumnos: instructor + admin.
    Route::middleware('role:instructor,admin')->group(function () {
        Route::get('/asistencia/alumnos', [MemberAttendanceController::class, 'index'])
            ->name('attendance.members.index');
        Route::get('/asistencia/alumnos/{session}', [MemberAttendanceController::class, 'show'])
            ->name('attendance.members.show');
        Route::post('/asistencia/alumnos/{session}', [MemberAttendanceController::class, 'store'])
            ->name('attendance.members.store');
    });

    // Instructores: coordinador + admin.
    Route::middleware('role:coordinator,admin')->group(function () {
        Route::get('/asistencia/instructores', [InstructorAttendanceController::class, 'index'])
            ->name('attendance.instructors.index');
        Route::post('/asistencia/instructores/{session}', [InstructorAttendanceController::class, 'store'])
            ->name('attendance.instructors.store');
    });

    // =====================================================================
    // REPORTES (T4) — solo admin
    // =====================================================================
    Route::middleware('role:admin')->group(function () {
        Route::get('/reportes', fn() => view('reports.index'))->name('reports.index');
        Route::get('/reportes/pago-instructores', [ReportController::class, 'payroll'])->name('reports.payroll');
        Route::get('/reportes/control-socios', [ReportController::class, 'memberControl'])->name('reports.member-control');
    });

    // =====================================================================
    // PAGOS (T5) — admin + recepción
    // =====================================================================
    Route::middleware('role:admin,receptionist')->group(function () {
        // Rutas específicas ANTES de las que llevan {member}.
        Route::get('/pagos', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/pagos/vencidos', [PaymentController::class, 'overdue'])->name('payments.overdue');

        Route::get('/pagos/socio/{member}', [PaymentController::class, 'member'])->name('payments.member');
        Route::post('/pagos/socio/{member}', [PaymentController::class, 'store'])->name('payments.store');
    });

    // =====================================================================
    // MANTENIMIENTO (T6) — admin + coordinador
    // =====================================================================
    Route::middleware('role:admin,coordinator')->group(function () {
        Route::get('/mantenimiento', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/mantenimiento', [MaintenanceController::class, 'store'])->name('maintenance.store');
        Route::put('/mantenimiento/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::patch('/mantenimiento/{maintenance}/toggle', [MaintenanceController::class, 'toggle'])->name('maintenance.toggle');
        Route::delete('/mantenimiento/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
    });
});
