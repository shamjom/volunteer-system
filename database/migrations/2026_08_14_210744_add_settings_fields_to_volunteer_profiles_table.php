<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_profiles', function (Blueprint $table) {

            $table->string('avatar')->nullable()->after('address');

            $table->json('interests')->nullable()->after('avatar');

            $table->unsignedInteger('preferred_hours_per_week')
                ->nullable()
                ->after('interests');

            $table->boolean('notifications_enabled')
                ->default(true)
                ->after('preferred_hours_per_week');

            $table->string('language', 5)
                ->default('ar')
                ->after('notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_profiles', function (Blueprint $table) {

            $table->dropColumn([
                'avatar',
                'interests',
                'preferred_hours_per_week',
                'notifications_enabled',
                'language',
            ]);
        });
    }
};