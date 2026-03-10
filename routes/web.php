<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\LivesetController;
use App\Http\Controllers\Admin\LivesetFilesController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\UtilController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\PosterController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListController::class, 'index'])->name('home');
Route::get('/live', fn () => Inertia\Inertia::render('Live'))->name('live');

// Versioned, cache-busting asset route for posters and images
Route::get('/images/{version}/{path}', [PosterController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.versioned-images');

// User authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'login'])->name('auth.login.post');
    Route::get('/register', [RegisterController::class, 'index'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'register'])->name('auth.register.post');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::get('/profile', [UserController::class, 'profile'])->name('user.profile');
    Route::get('/history', [UserController::class, 'history'])->name('user.history');
    Route::get('/favorites', [UserController::class, 'favorites'])->name('user.favorites');
    Route::get('/devices', [UserController::class, 'devices'])->name('user.devices');
    Route::get('/link', [UserController::class, 'linkDevice'])->name('user.link-device');
});

// Playlists - public overview + individual playlist view
Route::get('/playlists', [UserController::class, 'playlists'])->name('user.playlists');
Route::get('/playlists/{shareCode}/{slug?}', [UserController::class, 'playlist'])->name('playlist.show');

// Legacy shared playlist URL redirect
Route::get('/p/{code}', fn (string $code) => redirect()->route('playlist.show', ['shareCode' => $code]));

// Admin login (separate from user login)
Route::get('/admin/login', [AdminLoginController::class, 'index'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login.post');

Route::middleware('admin')->prefix('/admin')->group(function () {

    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/editions', [EditionController::class, 'editions'])->name('admin.editions');
    Route::get('/editions/new', [EditionController::class, 'newEdition'])->name('admin.editions.create');
    Route::post('/editions', [EditionController::class, 'storeEdition'])->name('admin.editions.store');
    Route::get('/editions/{edition}', [EditionController::class, 'viewEdition'])->name('admin.editions.view');
    Route::patch('/editions/{edition}', [EditionController::class, 'updateEdition'])->name('admin.editions.update');
    Route::delete('/editions/{edition}', [EditionController::class, 'deleteEdition'])->name('admin.editions.delete');

    Route::get('/editions/{edition}/poster', [EditionController::class, 'viewPoster'])->name('admin.editions.poster');
    Route::post('/editions/{edition}/poster', [EditionController::class, 'updatePoster'])->name('admin.editions.poster.update');

    Route::get('/livesets', [LivesetController::class, 'livesets'])->name('admin.livesets');
    Route::get('/livesets/new', [LivesetController::class, 'newLiveset'])->name('admin.livesets.create');
    Route::get('/livesets/{liveset}', [LivesetController::class, 'viewLiveset'])->name('admin.livesets.view');
    Route::post('/livesets', [LivesetController::class, 'storeLiveset'])->name('admin.livesets.store');
    Route::patch('/livesets/{liveset}', [LivesetController::class, 'updateLiveset'])->name('admin.livesets.update');
    Route::delete('/livesets/{liveset}', [LivesetController::class, 'deleteLiveset'])->name('admin.livesets.delete');

    Route::get('/livesets/{liveset}/files', [LivesetFilesController::class, 'livesetFiles'])->name('admin.livesets.files');
    Route::post('/livesets/{liveset}/files/import', [LivesetFilesController::class, 'importLivesetFile'])->name('admin.livesets.files.import');
    Route::patch('/livesets/{liveset}/files/{file}', [LivesetFilesController::class, 'editLivesetFile'])->name('admin.livesets.files.edit');
    Route::delete('/livesets/{liveset}/files/{file}', [LivesetFilesController::class, 'deleteLivesetFile'])->name('admin.livesets.files.delete');
    Route::post('/livesets/{liveset}/files/{file}/convert', [LivesetFilesController::class, 'convertLivesetFile'])->name('admin.livesets.files.convert');
    Route::post('/livesets/{liveset}/audiowaveform', [LivesetFilesController::class, 'generateAudiowaveform'])->name('admin.livesets.files.audiowaveform.generate');
    Route::delete('/livesets/{liveset}/audiowaveform', [LivesetFilesController::class, 'deleteAudiowaveform'])->name('admin.livesets.files.audiowaveform.delete');

    Route::get('/users', [AdminUserController::class, 'users'])->name('admin.users');
    Route::get('/users/new', [AdminUserController::class, 'newUser'])->name('admin.users.create');
    Route::post('/users', [AdminUserController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'viewUser'])->name('admin.users.view');
    Route::patch('/users/{user}', [AdminUserController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'deleteUser'])->name('admin.users.delete');

    Route::post('/util/cue', [UtilController::class, 'parseCue'])->name('util.cue');

});
