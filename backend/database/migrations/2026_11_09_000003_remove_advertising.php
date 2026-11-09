<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DeenQ AI is ad-free. Removes the ad-only app settings and the per-user "personalised ads"
 * preference. Nothing else is touched.
 */
return new class extends Migration
{
    private const AD_SETTINGS = ['admob_home_native_enabled', 'admob_learn_native_enabled', 'admob_learn_banner_enabled'];

    public function up(): void
    {
        DB::table('app_settings')->whereIn('key', self::AD_SETTINGS)->delete();

        if (Schema::hasColumn('user_preferences', 'ads_personalized')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->dropColumn('ads_personalized');
            });
        }
    }

    /** Restores the column only. The ad settings are not recreated, as nothing uses them. */
    public function down(): void
    {
        if (! Schema::hasColumn('user_preferences', 'ads_personalized')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->boolean('ads_personalized')->default(false);
            });
        }
    }
};
