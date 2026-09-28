<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the Fixobjekt calendar colour with the EMS brand gold (#D4AF37).
 *
 * The specification calls for recurring Fixobjekt appointments to be shown in
 * the signature gold. Objects that were deliberately given a custom colour keep
 * it — only the previous *default* (#FFD700) is remapped, so no deliberate
 * choice is overwritten.
 */
return new class extends Migration
{
    private const LEGACY_GOLD = '#FFD700';
    private const BRAND_GOLD  = '#D4AF37';

    public function up(): void
    {
        if (! Schema::hasTable('fix_objects')) {
            return;
        }

        DB::table('fix_objects')
            ->where('calendar_color', self::LEGACY_GOLD)
            ->update(['calendar_color' => self::BRAND_GOLD]);

        // ...and make it the default for newly created objects.
        Schema::table('fix_objects', function (Blueprint $table) {
            $table->string('calendar_color', 7)->default(self::BRAND_GOLD)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fix_objects')) {
            return;
        }

        DB::table('fix_objects')
            ->where('calendar_color', self::BRAND_GOLD)
            ->update(['calendar_color' => self::LEGACY_GOLD]);
    }
};
