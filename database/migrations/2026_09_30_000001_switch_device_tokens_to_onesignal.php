<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FCM is gone: OneSignal is the only mobile push provider.
     *
     * Existing `provider = 'fcm'` rows hold Firebase registration tokens, which
     * the OneSignal REST API cannot resolve. They are deleted rather than
     * deactivated so they stop consuming one of each user's five device slots;
     * affected phones simply re-register their OneSignal Player ID on next app
     * launch (and via POST /api/v1/device-tokens).
     */
    public function up(): void
    {
        DB::table('device_tokens')->where('provider', 'fcm')->delete();

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('provider', 12)->default('onesignal')->change();
        });
    }

    /**
     * The deleted FCM rows are intentionally not restored — the tokens in them
     * were revoked on the Firebase side by the app uninstall/reinstall that
     * triggered the re-registration.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('provider', 12)->default('fcm')->change();
        });
    }
};
