<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends extra_auftraege with the admin financial detail requested for
 * TeamUp-style order management:
 *   - price_per_hour  : hourly rate charged (encrypted)
 *   - profit_margin   =>  margin value (encrypted)
 *   - profit_margin_type -> whether the margin is a fixed Euro amount or a %
 * plus a single contract/attachment (image or PDF) for the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_auftraege', function (Blueprint $table) {
            $table->text('price_per_hour')->nullable()->after('price')
                  ->comment('encrypted — hourly rate');
            $table->text('profit_margin')->nullable()
                  ->comment('encrypted — profit margin value');
            $table->string('profit_margin_type', 3)->default('eur')
                  ->comment('eur | pct');

            // Attachment (contract / customer image) — private disk
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('extra_auftraege', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_hour',
                'profit_margin',
                'profit_margin_type',
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
            ]);
        });
    }
};
