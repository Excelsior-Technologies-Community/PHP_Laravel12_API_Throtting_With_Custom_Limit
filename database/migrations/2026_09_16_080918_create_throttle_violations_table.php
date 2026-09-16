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
        Schema::create('throttle_violations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->string('route_name')->nullable();
            $table->string('limiter')->nullable();
            $table->unsignedInteger('retry_after')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('ip_address');
            $table->index('user_id');
            $table->index('limiter');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('throttle_violations');
    }
};