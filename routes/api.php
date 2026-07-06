<?php

use App\Http\Controllers\Api\V1\Approval\ApprovalController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\Inspection\InspectionOrderController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\Template\InspectionTemplateController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardHrisController;
use App\Http\Controllers\Api\V1\Employee\EmployeeController;
use App\Http\Controllers\Api\V1\Hris\HrisMasterDataController;
use App\Http\Controllers\Api\V1\Hris\ShiftOvertimeController;
use App\Http\Controllers\Api\V1\Company\DepartmentController;
use App\Http\Controllers\Api\V1\Payroll\PayrollController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceController;
use App\Http\Controllers\Api\V1\Holiday\HolidayController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ─── Protected ────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::get('me',       [AuthController::class, 'me']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('summary',  [DashboardController::class, 'summary']);
        Route::get('recent',   [DashboardController::class, 'recent']);
        Route::get('stats',    [DashboardController::class, 'stats']);
        Route::get('notifications',             [DashboardController::class, 'notifications']);
        Route::put('notifications/read-all',    [DashboardController::class, 'markAllRead']);
        Route::put('notifications/{id}/read',   [DashboardController::class, 'markNotificationRead']);
    });

    Route::prefix('dashboard-hris')->group(function () {
        Route::get('summary', [DashboardHrisController::class, 'summary']);  
    });

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::prefix('hris-master-data/{resource}')->group(function () {
        Route::get('/', [HrisMasterDataController::class, 'index']);
        Route::post('/', [HrisMasterDataController::class, 'store']);
        Route::get('/{id}', [HrisMasterDataController::class, 'show']);
        Route::put('/{id}', [HrisMasterDataController::class, 'update']);
        Route::delete('/{id}', [HrisMasterDataController::class, 'destroy']);
    });

    Route::prefix('shiftments')->group(function () {
        Route::get('/', [ShiftOvertimeController::class, 'shiftments']);
        Route::post('/', [ShiftOvertimeController::class, 'storeShiftment']);
        Route::get('/{shiftment}', [ShiftOvertimeController::class, 'showShiftment']);
        Route::put('/{shiftment}', [ShiftOvertimeController::class, 'updateShiftment']);
        Route::delete('/{shiftment}', [ShiftOvertimeController::class, 'destroyShiftment']);
    });

    Route::prefix('workshifts')->group(function () {
        Route::get('/', [ShiftOvertimeController::class, 'workShifts']);
        Route::post('/', [ShiftOvertimeController::class, 'storeWorkShift']);
        Route::get('/{workshift}', [ShiftOvertimeController::class, 'showWorkShift']);
        Route::put('/{workshift}', [ShiftOvertimeController::class, 'updateWorkShift']);
        Route::delete('/{workshift}', [ShiftOvertimeController::class, 'destroyWorkShift']);
    });

    Route::prefix('overtimes')->group(function () {
        Route::get('/', [ShiftOvertimeController::class, 'overtimes']);
        Route::post('/', [ShiftOvertimeController::class, 'storeOvertime']);
        Route::get('/{overtime}', [ShiftOvertimeController::class, 'showOvertime']);
        Route::put('/{overtime}', [ShiftOvertimeController::class, 'updateOvertime']);
        Route::delete('/{overtime}', [ShiftOvertimeController::class, 'destroyOvertime']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/',             [UserController::class, 'index']);
        Route::post('/',            [UserController::class, 'store']);
        Route::get('/roles',        [UserController::class, 'roles']);
        Route::get('/{user}',       [UserController::class, 'show']);
        Route::put('/{user}',       [UserController::class, 'update']);
        Route::delete('/{user}',    [UserController::class, 'destroy']);
        Route::put('/{user}/role',  [UserController::class, 'assignRole']);
        Route::put('/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/',             [RoleController::class, 'index']);
        Route::post('/',            [RoleController::class, 'store']);
        Route::get('/{role}',       [RoleController::class, 'show']);
        Route::put('/{role}',       [RoleController::class, 'update']);
        Route::delete('/{role}',    [RoleController::class, 'destroy']);
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/',             [RoleController::class, 'permissions']);
        Route::put('/{rolePermission}',       [RoleController::class, 'permissionsUpdate']);
    });

    // ── Companies ─────────────────────────────────────────────────────────────
    Route::prefix('companies')->group(function () {
        Route::get('/',             [CompanyController::class, 'index']);
        Route::post('/',            [CompanyController::class, 'store']);
        Route::get('/{company}',    [CompanyController::class, 'show']);
        Route::put('/{company}',    [CompanyController::class, 'update']);
        Route::delete('/{company}', [CompanyController::class, 'destroy']);
    });

    Route::prefix('departments')->group(function () {
        Route::get('/',             [DepartmentController::class, 'index']);
        Route::post('/',            [DepartmentController::class, 'store']);
        Route::get('/{department}',    [DepartmentController::class, 'show']);
        Route::put('/{department}',    [DepartmentController::class, 'update']);
        Route::delete('/{department}', [DepartmentController::class, 'destroy']);
    });

    Route::prefix('employees')->group(function () {
        Route::get('/',             [EmployeeController::class, 'index']);
        Route::post('/',            [EmployeeController::class, 'store']);
        Route::get('/{employee}',    [EmployeeController::class, 'show']);
        Route::put('/{employee}',    [EmployeeController::class, 'update']);
        Route::delete('/{employee}', [EmployeeController::class, 'destroy']);
        Route::get('/export',        [EmployeeController::class, 'exportData']);
        Route::post('/import',       [EmployeeController::class, 'importData']);
    });

    Route::prefix('payrolls')->group(function () {
        Route::get('/',             [PayrollController::class, 'indexPeriod']);
        Route::post('/',            [PayrollController::class, 'store']);
        Route::get('/{payroll}',    [PayrollController::class, 'show']);
        Route::delete('/{payroll}', [PayrollController::class, 'destroy']);
        Route::get('/list/{period}', [PayrollController::class, 'indexPayrolls']);
        Route::get('/details/{payroll}', [PayrollController::class, 'payrollDetails']);
        Route::post('/import/{period}', [PayrollController::class, 'importData']);
        Route::post('/rollback', [PayrollController::class, 'rollbackLastImport']);
        Route::post('/rollback/{period}', [PayrollController::class, 'rollbackLastImport']);
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/',             [AttendanceController::class, 'index']);
        Route::post('/',            [AttendanceController::class, 'store']);
        Route::get('/{attendance}',    [AttendanceController::class, 'show']);
        Route::put('/{attendance}',    [AttendanceController::class, 'update']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);
        Route::get('/export',        [AttendanceController::class, 'exportData']);
        Route::post('/import',       [AttendanceController::class, 'importData']);
    });

    Route::prefix('holidays')->group(function () {
        Route::get('/',             [HolidayController::class, 'index']);
        Route::post('/',            [HolidayController::class, 'store']);
        Route::get('/{holiday}',    [HolidayController::class, 'show']);
        Route::put('/{holiday}',    [HolidayController::class, 'update']);
        Route::delete('/{holiday}', [HolidayController::class, 'destroy']);
    });

    // ── Form Templates ────────────────────────────────────────────────────────
    Route::prefix('templates')->group(function () {
        Route::get('/',                             [InspectionTemplateController::class, 'index']);
        Route::post('/',                            [InspectionTemplateController::class, 'store']);
        Route::get('/{template}',                   [InspectionTemplateController::class, 'show']);
        Route::put('/{template}',                   [InspectionTemplateController::class, 'update']);
        Route::delete('/{template}',                [InspectionTemplateController::class, 'destroy']);
        Route::post('/{template}/publish',          [InspectionTemplateController::class, 'publish']);
        Route::post('/{template}/duplicate',        [InspectionTemplateController::class, 'duplicate']);
        Route::get('/{template}/sections',          [InspectionTemplateController::class, 'sections']);
        Route::post('/{template}/sections',         [InspectionTemplateController::class, 'storeSection']);
    });

    // ── Sections ──────────────────────────────────────────────────────────────
    Route::prefix('sections')->group(function () {
        Route::put('/{section}',            [InspectionTemplateController::class, 'updateSection']);
        Route::delete('/{section}',         [InspectionTemplateController::class, 'destroySection']);
        Route::post('/{section}/items',     [InspectionTemplateController::class, 'storeCheckpoint']);
    });

    // ── Items ─────────────────────────────────────────────────────────────────
    Route::prefix('items')->group(function () {
        Route::put('/{item}',       [InspectionTemplateController::class, 'updateCheckpoint']);
        Route::delete('/{item}',    [InspectionTemplateController::class, 'destroyCheckpoint']);
        Route::post('/reorder',     [InspectionTemplateController::class, 'reorderCheckpoints']);
    });

    // ── Inspections ───────────────────────────────────────────────────────────
    Route::prefix('inspections')->group(function () {
        Route::get('/',                              [InspectionOrderController::class, 'index']);
        Route::post('/',                             [InspectionOrderController::class, 'store']);
        Route::get('/{inspection}',                  [InspectionOrderController::class, 'show']);
        Route::put('/{inspection}',                  [InspectionOrderController::class, 'update']);
        Route::delete('/{inspection}',               [InspectionOrderController::class, 'destroy']);
        Route::post('/{inspection}/submit',          [InspectionOrderController::class, 'submit']);
        Route::get('/{inspection}/approval-history', [InspectionOrderController::class, 'approvalHistory']);
        Route::post('/{inspection}/items',       [InspectionOrderController::class, 'storeItem']);
        Route::put('/{inspection}/items/{item}',              [InspectionOrderController::class, 'updateItem']);
        Route::delete('/{inspection}/items/{item}',           [InspectionOrderController::class, 'destroyItem']);

        
    });

    Route::prefix('inspection-types')->group(function () {
        Route::get('/', [InspectionOrderController::class, 'inspectionsType']);
    });
    

    // ── Approvals ─────────────────────────────────────────────────────────────
    Route::prefix('approvals')->group(function () {
        Route::get('/',                     [ApprovalController::class, 'index']);
        Route::get('/{approval}',           [ApprovalController::class, 'show']);
        Route::post('/{approval}/approve',  [ApprovalController::class, 'approve']);
        Route::post('/{approval}/reject',   [ApprovalController::class, 'reject']);
        Route::post('/{approval}/revise',   [ApprovalController::class, 'revise']);
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('/',                  [ReportController::class, 'index']);
        Route::post('export/pdf',        [ReportController::class, 'exportPdf']);
        Route::post('export/excel',      [ReportController::class, 'exportExcel']);
    });

});

Route::post('/webhook', [TelegramController::class, 'webhook']);
Route::post('/telegram/register', [TelegramController::class, 'register']);
Route::post('/payroll/slip', [TelegramController::class, 'telegramSlip']);
