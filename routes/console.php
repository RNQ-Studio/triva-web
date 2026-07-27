<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pembersihan asset dua fase. withoutOverlapping mencegah tumpang tindih bila eksekusi lama.
Schedule::command('assets:soft-delete-expired')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
Schedule::command('assets:hard-delete-expired')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
Schedule::command('assets:cleanup-orphan-toyota-service-photos')
    ->dailyAt('01:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('toyota-service:expire-alternatives')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
