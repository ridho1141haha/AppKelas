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
        Schema::table('schedules', function (Blueprint $table) {
            // Hapus kolom lama yang gak dipake lagi
            $table->dropColumn(['start_time', 'end_time', 'subject', 'teacher']);

            // Tambah kolom baru sesuai request
            $table->string('type')->after('day')->comment('Normatif-Adaptif / Produktif M1 / Produktif M2');
            $table->text('subjects')->after('type');
            $table->string('dismissal_time')->after('subjects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Balikin kolom lama kalau rollback
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('subject')->nullable();
            $table->string('teacher')->nullable();

            // Hapus kolom baru
            $table->dropColumn(['type', 'subjects', 'dismissal_time']);
        });
    }
};
