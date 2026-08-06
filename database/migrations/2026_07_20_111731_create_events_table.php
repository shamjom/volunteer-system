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
    Schema::create('events', function (Blueprint $table) {

        $table->id();
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('location');
        $table->dateTime('start_date');
        $table->dateTime('end_date');
        $table->integer('max_volunteers');
        $table->enum('status',[
            'open',
            'closed',
            'completed'
        ])->default('open');

        $table->foreignId('created_by')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->timestamps();

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }

    public function tasks()
    {
    return $this->hasMany(Task::class);
    }
};
