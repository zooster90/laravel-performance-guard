<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('method', 10);
            $table->string('uri', 2048);
            $table->text('controller')->nullable();
            $table->text('action')->nullable();
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedInteger('slow_query_count')->default(0);
            $table->decimal('duration_ms', 10, 2);
            $table->decimal('memory_mb', 10, 2);
            $table->char('grade', 1);
            $table->boolean('has_n_plus_one')->default(false);
            $table->boolean('has_slow_queries')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('grade');
            $table->index('has_n_plus_one');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');
    }
};
