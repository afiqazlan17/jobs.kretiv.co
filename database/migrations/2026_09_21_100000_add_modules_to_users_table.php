<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kretiv OS module access. NULL = "use the role's default" (see
    // config kretivco.module_defaults), so existing accounts keep working
    // without any data backfill; BOD saving an explicit list overrides it.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('visible_departments');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
