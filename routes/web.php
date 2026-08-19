<?php

use App\Http\Controllers\PublicArticleController;
use App\Http\Middleware\RecordLandingPageVisit;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware(RecordLandingPageVisit::class);

Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');
Route::redirect('/privacy', '/privacy-policy', 301);
Route::redirect('/account-deletion', '/privacy-policy#penghapusan-akun')
    ->name('account-deletion');

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');
