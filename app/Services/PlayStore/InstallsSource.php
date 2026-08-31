<?php

namespace App\Services\PlayStore;

use App\Support\PlayStoreInstalls;

interface InstallsSource
{
    /**
     * Angka pemasangan terbaru, atau null kalau sumber ini belum terkonfigurasi
     * atau tidak punya angka yang bisa dipercaya.
     */
    public function fetch(): ?PlayStoreInstalls;
}
