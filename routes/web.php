<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\LivesetController;
use App\Http\Controllers\Admin\LivesetFilesController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\UtilController;
use App\Http\Controllers\ListController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ ListController::class, 'index'])->name('home');


Route::get('/login', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');


Route::middleware('admin')->prefix('/admin')->group(function () {

    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/editions', [EditionController::class, 'editions'])->name('admin.editions');
    Route::get('/editions/new', [EditionController::class, 'newEdition'])->name('admin.editions.create');
    Route::post('/editions', [EditionController::class, 'storeEdition'])->name('admin.editions.store');
    Route::get('/editions/{edition}', [EditionController::class, 'viewEdition'])->name('admin.editions.view');
    Route::patch('/editions/{edition}', [EditionController::class, 'updateEdition'])->name('admin.editions.update');
    Route::delete('/editions/{edition}', [EditionController::class, 'deleteEdition'])->name('admin.editions.delete');

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




    Route::post('/util/cue', [UtilController::class, 'parseCue'])->name('util.cue');

});
