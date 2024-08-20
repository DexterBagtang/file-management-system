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
        Schema::create('shared_items', function (Blueprint $table) {
            $table->id();
            $table->enum('item_type', ['folder', 'file']);
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('shared_with_id');
            $table->enum('permission', ['read', 'write']);
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('shared_with_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_items');
    }
};
