<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('volunteer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('registration_status', [
                'pending',
                'approved',
                'rejected',
                'cancelled'
            ])->default('pending');

            $table->enum('attendance_status', [
                'pending',
                'present',
                'absent'
            ])->default('pending');

            $table->unsignedInteger('volunteer_hours')->default(0);

            $table->timestamp('registered_at')->useCurrent();

            $table->timestamps();

            $table->unique([
                'event_id',
                'volunteer_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};