<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Memindahkan lima logo mitra yang selama ini ditanam di aplikasi menjadi
 * data awal tabel `partner_logos`, supaya beranda tetap sama persis saat
 * aplikasi mulai membaca daftar mitra dari server.
 *
 * Urutan mengikuti notulensi 19 Agustus 2026 (Auto2000 Kertajaya, OtoXpert,
 * OLX, ACC, TAF). Berkas sumber disimpan di repo dan disalin ke disk
 * `public`; tautan resmi diverifikasi 6 Oktober 2026.
 */
return new class extends Migration
{
    /** @var list<array{file: string, name: string, link_url: string|null}> */
    private const LOGOS = [
        ['file' => 'auto2000.png', 'name' => 'Auto2000', 'link_url' => 'https://auto2000.co.id'],
        ['file' => 'otoxpert.png', 'name' => 'OtoXpert', 'link_url' => 'https://otoxpert.co.id'],
        ['file' => 'olx.png', 'name' => 'OLX', 'link_url' => 'https://www.olx.co.id'],
        ['file' => 'acc.png', 'name' => 'ACC', 'link_url' => 'https://www.acc.co.id'],
        ['file' => 'taf.png', 'name' => 'TAF', 'link_url' => 'https://taf.co.id'],
    ];

    private const DIRECTORY = 'partner-logos';

    public function up(): void
    {
        // Test memakai daftar mitra buatannya sendiri dan tidak boleh menulis
        // ke disk publik sungguhan; penyemaian diuji lewat seed() langsung.
        if (app()->runningUnitTests()) {
            return;
        }

        $this->seed();
    }

    /**
     * Idempoten: logo yang sudah diatur admin tidak pernah ditimpa, dan
     * berkas yang sudah ada di disk tidak disalin ulang.
     */
    public function seed(): void
    {
        if (DB::table('partner_logos')->exists()) {
            return;
        }

        $disk = Storage::disk('public');
        $now = now();

        foreach (self::LOGOS as $index => $logo) {
            $path = self::DIRECTORY.'/'.$logo['file'];
            $source = database_path('seeders/data/partner-logos/'.$logo['file']);

            if (! $disk->exists($path)) {
                $contents = @file_get_contents($source);
                if ($contents === false) {
                    continue;
                }
                $disk->put($path, $contents);
            }

            DB::table('partner_logos')->insert([
                'id' => (string) Str::orderedUuid(),
                'name' => $logo['name'],
                'logo_path' => $path,
                'link_url' => $logo['link_url'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->matchDiskOwnership();
    }

    /**
     * Migrasi yang dijalankan manual sebagai root akan membuat folder milik
     * root, sehingga unggahan admin lewat PHP-FPM (www-data) gagal menulis.
     * Samakan pemiliknya dengan akar disk publik.
     */
    private function matchDiskOwnership(): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }

        $disk = Storage::disk('public');
        $root = $disk->path('');
        $owner = @fileowner($root);
        $group = @filegroup($root);
        if ($owner === false || $group === false) {
            return;
        }

        $paths = [$disk->path(self::DIRECTORY)];
        foreach ($disk->files(self::DIRECTORY) as $file) {
            $paths[] = $disk->path($file);
        }

        foreach ($paths as $path) {
            @chown($path, $owner);
            @chgrp($path, $group);
        }
    }

    public function down(): void
    {
        $paths = array_map(
            fn (array $logo): string => self::DIRECTORY.'/'.$logo['file'],
            self::LOGOS,
        );

        DB::table('partner_logos')->whereIn('logo_path', $paths)->delete();
    }
};
