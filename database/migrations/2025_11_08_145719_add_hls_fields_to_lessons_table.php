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
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('hls_playlist')->nullable()->after('video');
            $table->string('encryption_key_id')->nullable()->after('hls_playlist');
            $table->text('encryption_key')->nullable()->after('encryption_key_id');
            $table->boolean('hls_processing')->default(false)->after('encryption_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['hls_playlist', 'encryption_key_id', 'encryption_key', 'hls_processing']);
        });
    }
};
