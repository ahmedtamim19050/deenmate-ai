<?php

use App\Http\Controllers\Api\V1\AchievementController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookmarkController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\HadithController;
use App\Http\Controllers\Api\V1\LearningController;
use App\Http\Controllers\Api\V1\PointsController;
use App\Http\Controllers\Api\V1\PrayerController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QuranController;
use App\Http\Controllers\Api\V1\SalahController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Public educational content, so guests can explore before creating an account.
    Route::middleware('throttle:public')->group(function () {
        Route::get('config', [ConfigController::class, 'show']);
        Route::get('courses', [LearningController::class, 'courses']);
        Route::get('courses/{course}', [LearningController::class, 'course']);
        Route::get('lessons/{lesson}', [LearningController::class, 'lesson']);
        Route::get('quran/surahs', [QuranController::class, 'surahs']);
        Route::get('quran/surahs/{surah}', [QuranController::class, 'surah']);
        Route::get('quran/editions', [QuranController::class, 'editions']);
        Route::get('quran/credits', [QuranController::class, 'credits']);
        Route::get('quran/search', [QuranController::class, 'search']);
        Route::get('hadith/collections', [HadithController::class, 'collections']);
        Route::get('hadith/collections/{collection}/records', [HadithController::class, 'records']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/devices', [AuthController::class, 'registerDevice']);

        Route::put('profile', [ProfileController::class, 'update']);
        Route::get('profile/preferences', [ProfileController::class, 'preferences']);
        Route::put('profile/preferences', [ProfileController::class, 'updatePreferences']);

        Route::get('dashboard', [DashboardController::class, 'show']);
        Route::get('progress', [DashboardController::class, 'progress']);

        Route::get('activities/types', [ActivityController::class, 'types']);
        Route::post('activities', [ActivityController::class, 'store']);
        Route::post('activities/sync', [ActivityController::class, 'sync'])->middleware('throttle:sync');
        Route::get('activities/history', [ActivityController::class, 'history']);
        Route::put('activities/{activity}', [ActivityController::class, 'update']);
        Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);

        Route::get('points/balance', [PointsController::class, 'balance']);
        Route::get('points/history', [PointsController::class, 'history']);
        Route::get('points/summary', [PointsController::class, 'summary']);

        Route::get('achievements', [AchievementController::class, 'index']);
        Route::get('achievements/mine', [AchievementController::class, 'mine']);

        Route::get('goals', [GoalController::class, 'index']);
        Route::post('goals', [GoalController::class, 'store']);
        Route::put('goals/{goal}', [GoalController::class, 'update']);
        Route::delete('goals/{goal}', [GoalController::class, 'destroy']);

        Route::get('prayer/today', [PrayerController::class, 'today']);
        Route::get('prayer/methods', [PrayerController::class, 'methods']);
        Route::get('prayer/settings', [PrayerController::class, 'settings']);
        Route::put('prayer/settings', [PrayerController::class, 'updateSettings']);
        Route::get('prayer/templates', [PrayerController::class, 'templates']);
        Route::put('prayer/templates', [PrayerController::class, 'updateTemplates']);
        Route::post('prayer/templates/restore', [PrayerController::class, 'restoreTemplates']);
        Route::get('prayer/history', [PrayerController::class, 'history']);

        Route::post('salah/confirm', [SalahController::class, 'confirm']);
        Route::get('salah/summary', [SalahController::class, 'summary']);
        Route::put('salah/{salah}', [SalahController::class, 'update']);
        Route::post('salah/{salah}/reverse', [SalahController::class, 'reverse']);

        Route::middleware('throttle:ai')->post('ai/chat', [AiController::class, 'chat']);
        Route::get('ai/conversations', [AiController::class, 'conversations']);
        Route::get('ai/conversations/{conversation}', [AiController::class, 'conversation']);
        Route::delete('ai/conversations/{conversation}', [AiController::class, 'destroyConversation']);
        Route::delete('ai/conversations', [AiController::class, 'clearConversations']);
        Route::post('ai/feedback', [AiController::class, 'feedback']);

        Route::post('lessons/{lesson}/complete', [LearningController::class, 'complete']);
        Route::post('quizzes/{quiz}/attempts', [LearningController::class, 'attempt']);

        Route::get('bookmarks', [BookmarkController::class, 'index']);
        Route::post('bookmarks', [BookmarkController::class, 'store']);
        Route::delete('bookmarks/{bookmark}', [BookmarkController::class, 'destroy']);

        Route::post('privacy/export', [PrivacyController::class, 'requestExport']);
        Route::get('privacy/export/{uuid}', [PrivacyController::class, 'exportStatus']);
        Route::get('privacy/export/{uuid}/download', [PrivacyController::class, 'download']);
        Route::post('privacy/delete-account', [PrivacyController::class, 'deleteAccount']);
    });
});
