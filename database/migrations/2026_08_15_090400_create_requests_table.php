<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('type', [
                'join_team',
                'help',
                'volunteering',
            ]);

            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->string('subject');

            $table->text('body')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->text('admin_note')->nullable();

            $table->timestamp('submitted_at')->useCurrent();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
        });

        // منع طلب انضمام معلّق مكرر لنفس الفريق — على مستوى قاعدة البيانات.
        // العمود يساوي NULL لكل ما ليس (join_team + pending)، و MySQL يسمح
        // بتكرار NULL في الفهرس الفريد، فالقيد يطبَّق على الحالة المقصودة فقط.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE requests
            ADD COLUMN pending_join_key VARCHAR(64)
            GENERATED ALWAYS AS (
                CASE
                    WHEN type = 'join_team' AND status = 'pending'
                    THEN CONCAT(user_id, ':', team_id)
                    ELSE NULL
                END
            ) STORED
        ");

        DB::statement('
            ALTER TABLE requests
            ADD UNIQUE INDEX requests_pending_join_key_unique (pending_join_key)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
