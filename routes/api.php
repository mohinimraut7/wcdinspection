<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\StateAdminController;
use App\Http\Controllers\DistrictAdminController;
use App\Http\Controllers\InspectionOfficerController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SurveyAssignmentController;
use App\Http\Controllers\InspectionReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrgSurveyController;

// ============================================================
// SUPER ADMIN ROUTES — /api/super-admin/...
// ============================================================
Route::prefix('super-admin')->group(function () {
    Route::post('/add',         [SuperAdminController::class, 'add']);
    Route::post('/login',       [SuperAdminController::class, 'login']);
    Route::post('/send-otp',    [SuperAdminController::class, 'sendOtp']);
    Route::post('/verify-otp',  [SuperAdminController::class, 'verifyOtp']);

    Route::middleware('auth.jwt:superadmin')->group(function () {
        Route::get('/get',          [SuperAdminController::class, 'get']);
        Route::put('/edit/{id}',    [SuperAdminController::class, 'edit']);
        Route::delete('/delete/{id}', [SuperAdminController::class, 'delete']);
    });
});

// ============================================================
// STATE ADMIN ROUTES — /api/state-admin/...
// ============================================================
Route::prefix('state-admin')->group(function () {
    Route::post('/login', [StateAdminController::class, 'login']);

    Route::middleware('auth.jwt:superadmin')->group(function () {
        Route::post('/add',           [StateAdminController::class, 'add']);
        Route::get('/get',            [StateAdminController::class, 'get']);
        Route::put('/edit/{id}',      [StateAdminController::class, 'edit']);
        Route::delete('/delete/{id}', [StateAdminController::class, 'delete']);
    });
});

// ============================================================
// DISTRICT ADMIN ROUTES — /api/district-admin/...
// ============================================================
Route::prefix('district-admin')->group(function () {
    Route::post('/login', [DistrictAdminController::class, 'login']);

    Route::middleware('auth.jwt:superadmin')->group(function () {
        Route::post('/add',           [DistrictAdminController::class, 'add']);
        Route::get('/get',            [DistrictAdminController::class, 'get']);
        Route::put('/edit/{id}',      [DistrictAdminController::class, 'edit']);
        Route::delete('/delete/{id}', [DistrictAdminController::class, 'delete']);
    });
});

// ============================================================
// INSPECTION OFFICER ROUTES — /api/inspection-officer/...
// ============================================================
Route::prefix('inspection-officer')->group(function () {
    Route::post('/login', [InspectionOfficerController::class, 'login']);

    // Route::middleware('auth.jwt:districtadmin')->group(function () {
    //     Route::post('/add',           [InspectionOfficerController::class, 'add']);
    //     Route::get('/get',            [InspectionOfficerController::class, 'get']);
    //     Route::put('/edit/{id}',      [InspectionOfficerController::class, 'edit']);
    //     Route::delete('/delete/{id}', [InspectionOfficerController::class, 'delete']);
    // });

Route::middleware('auth.jwt:districtadmin')->group(function () {
    Route::post('/add',           [InspectionOfficerController::class, 'add']);
    Route::put('/edit/{id}',      [InspectionOfficerController::class, 'edit']);
    Route::delete('/delete/{id}', [InspectionOfficerController::class, 'delete']);
});

Route::get('/get', [InspectionOfficerController::class, 'get'])
    ->middleware('auth.jwt:superadmin,stateadmin,districtadmin,inspectionofficer');

});

// ============================================================
// ORGANIZATION ROUTES — /api/org/...
// ============================================================
Route::prefix('org')->group(function () {
    Route::post('/register', [OrganizationController::class, 'register']);
    Route::post('/login',    [OrganizationController::class, 'login']);

    Route::post('/login-username', [OrganizationController::class, 'loginWithUsername']);
    Route::post('/send-otp',       [OrganizationController::class, 'sendOtp']);
    Route::post('/verify-otp',     [OrganizationController::class, 'verifyOtp']);

    Route::middleware('auth.jwt:organization')->group(function () {
        Route::get('/profile',        [OrganizationController::class, 'profile']);
        Route::put('/profile/edit',   [OrganizationController::class, 'profileEdit']);
        // Route::post('/survey/submit', [OrganizationController::class, 'surveySubmit']);
        Route::get('/survey/my',      [OrganizationController::class, 'mySurvey']);

        Route::post('/survey/submit', [OrgSurveyController::class, 'submit']);

    });
});

// ============================================================
// SURVEY ASSIGNMENT ROUTES — /api/assignment/...
// ============================================================
Route::prefix('assignment')->middleware('auth.jwt:districtadmin')->group(function () {
    Route::post('/assign',          [SurveyAssignmentController::class, 'assign']);
    Route::put('/deassign/{id}',    [SurveyAssignmentController::class, 'deassign']);
    Route::get('/get',              [SurveyAssignmentController::class, 'get']);
});

// ============================================================
// INSPECTION OFFICER REPORT ROUTES — /api/officer/...
// ============================================================
Route::prefix('officer')->middleware('auth.jwt:inspectionofficer')->group(function () {
    Route::get('/assignments',           [InspectionReportController::class, 'getMyAssignments']);
    Route::post('/report/submit',        [InspectionReportController::class, 'submitReport']);
    Route::get('/report/get',            [InspectionReportController::class, 'getMyReports']);
    Route::put('/report/reinspect/{id}', [InspectionReportController::class, 'reinspectReport']);
   Route::post('/report/quick-review',  [InspectionReportController::class, 'quickReview']); // ← हे add कर

});

// ============================================================
// DASHBOARD ROUTES — /api/dashboard/...
// ============================================================
Route::prefix('dashboard')->group(function () {
    Route::get('/superadmin',    [DashboardController::class, 'superAdmin'])->middleware('auth.jwt:superadmin');
    Route::get('/stateadmin',    [DashboardController::class, 'stateAdmin'])->middleware('auth.jwt:stateadmin');
    Route::get('/districtadmin', [DashboardController::class, 'districtAdmin'])->middleware('auth.jwt:districtadmin');
    Route::get('/officer',       [DashboardController::class, 'officer'])->middleware('auth.jwt:inspectionofficer');
});


// ============================================================
// SURVEY ROUTES — /api/surveys/...
// ============================================================
// Route::prefix('surveys')->group(function () {
//     Route::get('/',    [OrgSurveyController::class, 'getAllSurveys'])
//         ->middleware('auth.jwt:districtadmin,stateadmin,superadmin');

//     Route::get('/{id}', [OrgSurveyController::class, 'getSurveyDetail'])
//         ->middleware('auth.jwt:districtadmin,stateadmin,superadmin');
// });

Route::prefix('surveys')->group(function () {
    Route::get('/',    [OrgSurveyController::class, 'getAllSurveys'])
        ->middleware('auth.jwt:districtadmin,stateadmin,superadmin,inspectionofficer');

    Route::get('/{id}', [OrgSurveyController::class, 'getSurveyDetail'])
        ->middleware('auth.jwt:districtadmin,stateadmin,superadmin,inspectionofficer');
});





