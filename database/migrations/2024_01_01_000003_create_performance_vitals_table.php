<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_vitals', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->decimal('lcp_ms', 10, 2)->nullable();
            $table->decimal('cls_score', 8, 4)->nullable();
            $table->decimal('inp_ms', 10, 2)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_vitals');
    }
};
