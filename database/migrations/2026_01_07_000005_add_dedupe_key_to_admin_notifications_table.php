<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->string('dedupe_key', 191)
                ->nullable()
                ->index()
                ->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropIndex(['dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
    }
};