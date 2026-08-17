<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
   {
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('volunteer');
        $table->string('status')->default('pending');
    });
}

   public function down(): void
   {
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['role', 'status']);
    });
}
};
