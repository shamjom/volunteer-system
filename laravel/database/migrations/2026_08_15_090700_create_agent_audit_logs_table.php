<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_audit_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tool', 64);

            $table->json('arguments')->nullable();

            $table->boolean('ok')->default(true);

            $table->string('error_code', 32)->nullable();

            $table->uuid('confirmation_id')->nullable();

            $table->string('ip', 45)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_audit_logs');
    }
};
