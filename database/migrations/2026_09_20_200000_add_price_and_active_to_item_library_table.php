<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The library now drives the line-item dropdown on New Job and the
    // document modal, so items carry a default price and can be hidden
    // (instead of deleted) once they stop being offered.
    public function up(): void
    {
        Schema::table('item_library', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('description');
            $table->boolean('active')->default(true)->after('usage_count');
        });
    }

    public function down(): void
    {
        Schema::table('item_library', function (Blueprint $table) {
            $table->dropColumn(['price', 'active']);
        });
    }
};
