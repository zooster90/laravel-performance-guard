<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->index(['method', 'uri', 'created_at'], 'perf_records_route_aggregation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->dropIndex('perf_records_route_aggregation_idx');
        });
    }
};
