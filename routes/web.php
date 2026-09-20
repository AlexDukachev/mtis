<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1')->name('login.submit');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', ActiveUser::class])->group(function () {
    Route::view('/', 'workspace')->name('workspace');
    Route::view('/api-docs', 'api-docs')->name('api.docs');
    Route::prefix('api/v1')->middleware('throttle:180,1')->group(function () {
        Route::get('/bootstrap', [WorkspaceController::class, 'bootstrap']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/roadmap', [RoadmapController::class, 'index']);
        Route::post('/roadmap', [RoadmapController::class, 'save']);
        Route::put('/roadmap/{roadmapItem}', [RoadmapController::class, 'save']);
        Route::get('/users', [WorkspaceController::class, 'users']);
        Route::get('/teams', [WorkspaceController::class, 'teams']);
        Route::get('/projects', [WorkspaceController::class, 'projects']);
        Route::get('/reports/dashboard', [WorkspaceController::class, 'dashboard']);
        Route::get('/reports/workload', [WorkspaceController::class, 'workload']);
        Route::get('/issues', [IssueController::class, 'index']);
        Route::post('/issues', [IssueController::class, 'store']);
        Route::get('/issues/{issue}', [IssueController::class, 'show']);
        Route::patch('/issues/{issue}', [IssueController::class, 'update']);
        Route::post('/issues/{issue}/transitions', [IssueController::class, 'transition']);
        Route::post('/issues/{issue}/comments', [IssueController::class, 'comment']);
        Route::post('/issues/{issue}/worklogs', [IssueController::class, 'worklog']);
        Route::post('/issues/{issue}/attachments', [IssueController::class, 'upload']);
        Route::get('/issues/{issue}/{collection}', [IssueController::class, 'collection'])->whereIn('collection', ['comments', 'worklogs', 'attachments']);
        Route::get('/attachments/{attachment}', [IssueController::class, 'download'])->name('attachment.download');
        Route::get('/notifications', [WorkspaceController::class, 'notifications']);
        Route::post('/notifications/read', [WorkspaceController::class, 'readNotifications']);
        Route::put('/notifications/preferences', [WorkspaceController::class, 'preferences']);
        Route::post('/filters', [WorkspaceController::class, 'saveFilter']);
        Route::delete('/filters/{filter}', [WorkspaceController::class, 'deleteFilter']);
        Route::get('/admin', [AdminController::class, 'index']);
        Route::post('/users', [AdminController::class, 'user']);
        Route::put('/users/{user}', [AdminController::class, 'user']);
        Route::post('/teams', [AdminController::class, 'team']);
        Route::put('/teams/{team}', [WorkspaceController::class, 'updateTeam']);
        Route::post('/projects', [AdminController::class, 'project']);
        Route::put('/projects/{project}', [AdminController::class, 'project']);
        Route::put('/projects/{project}/workflow', [AdminController::class, 'workflow']);
        Route::put('/settings', [AdminController::class, 'settings']);
        Route::post('/calendar', [AdminController::class, 'calendar']);
    });
});
