<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_profiles', function (Blueprint $table) {

            $table->string('city')->nullable()->after('phone');

            $table->string('emergency_contact')->nullable()->after('city');

            // أربع قيم مغلقة. rejected لا يُشرح سببه للمتطوع إطلاقاً.
            $table->enum('cv_status', [
                'not_received',
                'received',
                'approved',
                'rejected',
            ])->default('not_received')->after('emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_profiles', function (Blueprint $table) {
            $table->dropColumn(['city', 'emergency_contact', 'cv_status']);
        });
    }
};
