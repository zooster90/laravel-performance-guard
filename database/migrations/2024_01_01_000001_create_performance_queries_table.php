<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_record_id');
            $table->text('sql');
            $table->text('normalized_sql')->nullable();
            $table->decimal('duration_ms', 10, 2);
            $table->boolean('is_slow')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('performance_record_id')
                ->references('id')
                ->on('performance_records')
                ->cascadeOnDelete();

            $table->index('performance_record_id');
            $table->index('is_slow');
            $table->index('is_duplicate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_queries');
    }
};
