<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings', [UserController::class, 'index'])->name('settings.index');
    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::post('/settings/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('settings.users.toggle-active');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');

    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::post('/jobs/{job}/take-in', [JobController::class, 'takeIn'])->name('jobs.take-in');
    Route::post('/jobs/{job}/close-ticket', [JobController::class, 'closeTicket'])->name('jobs.close-ticket');
    Route::post('/jobs/{job}/complete', [JobController::class, 'complete'])->name('jobs.complete');
    Route::post('/jobs/{job}/attachments', [AttachmentController::class, 'store'])->name('jobs.attachments.store');
    Route::get('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'show'])->name('jobs.attachments.show');
    Route::delete('/jobs/{job}/attachments/{attachmentId}', [AttachmentController::class, 'destroy'])->name('jobs.attachments.destroy');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    Route::post('/leads/{lead}/mark-lost', [LeadController::class, 'markLost'])->name('leads.mark-lost');
});

require __DIR__.'/auth.php';
