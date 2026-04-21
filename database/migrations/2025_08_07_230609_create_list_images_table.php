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
        Schema::create('list_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->nullable()->constrained('list_stories')->onDelete('cascade');
            $table->string('image_name')->nullable();
            $table->string('path')->nullable();
            $table->string('hash_name')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_images');
    }
};
