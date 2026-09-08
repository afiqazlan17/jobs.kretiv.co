<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            // [{id, vendor_id, estimated_cost, actual_cost, notes, status
            // ('unpaid'/'paid'), paid_date, paid_bank}, ...] — mirrors the
            // old app's Job.vendor_costs. Marking an entry paid posts a
            // real Finance ledger expense (see JobVendorCostController).
            $table->json('vendor_costs')->nullable()->after('cost_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('vendor_costs');
        });
    }
};
