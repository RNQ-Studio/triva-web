<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('body_paint_damage_photos', function (Blueprint $table): void {
            $table->unsignedBigInteger('replaces_photo_id')->nullable();
            $table->foreign('replaces_photo_id', 'bp_photo_replaces_fk')
                ->references('id')
                ->on('body_paint_damage_photos')
                ->restrictOnDelete();
            $table->unique('replaces_photo_id', 'bp_photo_replaces_unique');
        });
    }

    public function down(): void
    {
        Schema::table('body_paint_damage_photos', function (Blueprint $table): void {
            $table->dropForeign('bp_photo_replaces_fk');
            $table->dropUnique('bp_photo_replaces_unique');
            $table->dropColumn('replaces_photo_id');
        });
    }
};
