<?php

use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\LoanController as AdminLoanController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Laravel's own liveness check lives at /up (see bootstrap/app.php). This is
// a readiness check — confirms the database is actually reachable, for a
// deployment platform's load balancer/orchestrator to gate traffic on.
//
// Deliberately skips the whole 'web' middleware group: with
// SESSION_DRIVER=database, StartSession (and ShareErrorsFromSession, which
// also touches the session) would try to query the DB before this route's
// own try/catch ever runs, turning "database is down" into an unrelated 500
// instead of this route's clean 503 JSON — defeating the point of the check.
// No session/CSRF/cookies are needed for a read-only, unauthenticated probe.
Route::get('/up/db', [HealthController::class, 'db'])
    ->withoutMiddleware('web')
    ->name('health.db');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{book}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::post('/catalog/{book}/borrow', [LoanController::class, 'borrow'])->name('catalog.borrow');

    Route::get('/my-loans', [LoanController::class, 'myLoans'])->name('loans.mine');
    Route::post('/my-loans/{loan}/return', [LoanController::class, 'returnBook'])->name('loans.return');

    Route::get('/assistant', fn () => Inertia::render('Assistant/Index'))->name('assistant');
    Route::post('/assistant/query', [AssistantController::class, 'query'])
        ->middleware('throttle:assistant')
        ->name('assistant.query');

    Route::prefix('admin')->name('admin.')->middleware('staff')->group(function () {
        Route::resource('books', AdminBookController::class)->except(['show']);

        Route::get('loans', [AdminLoanController::class, 'index'])->name('loans.index');
        Route::post('loans', [AdminLoanController::class, 'store'])->name('loans.store');

        Route::middleware('admin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
