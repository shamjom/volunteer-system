<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_confirmations', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tool', 64);

            // بصمة الأداة + معاملاتها — تمنع إعادة استخدام تأكيد لعملية أخرى
            $table->char('fingerprint', 64);

            $table->timestamp('expires_at');

            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'tool']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_confirmations');
    }
};
