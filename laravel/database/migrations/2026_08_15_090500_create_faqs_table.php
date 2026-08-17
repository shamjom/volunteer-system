<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {

            $table->id();

            $table->string('question');

            $table->text('answer');

            $table->string('category')->nullable();

            $table->text('keywords')->nullable();

            // نسخة مطبَّعة من السؤال والكلمات المفتاحية، يملؤها الموديل
            $table->text('normalized_text')->nullable();

            $table->boolean('is_published')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
