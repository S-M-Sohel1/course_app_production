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
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('user_token', 50);
            $table->foreign('user_token')->references('token')->on('members')->onDelete('cascade');
            $table->unique('user_token');
            $table->string('avatar')->nullable();            
            $table->string('cover')->nullable();
            $table->float('rating')->nullable();
            $table->mediumInteger('downloads')->nullable();
            $table->mediumInteger('total_students')->nullable();
            $table->integer('experience_years')->nullable();
            $table->string('job')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_profiles');
    }
};
