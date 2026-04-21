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
        Schema::create('list_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('list_categories')->onDelete('cascade');
            //    $table->foreignId('category_id')
            //   ->constrained('list_categories')
            //   ->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('old_price')->nullable();
            $table->string('new_price')->nullable();
            $table->string('location')->nullable();
            $table->string('description')->nullable();
            $table->enum('condition', ['new', 'used'])->default('new');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_stories');
    }
};
