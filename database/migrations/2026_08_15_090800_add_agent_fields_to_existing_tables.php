<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('registered_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('last_status_note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('last_status_note');
        });
    }
};
