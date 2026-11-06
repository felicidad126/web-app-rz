<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7: pemeriksaan intraoral per kunjungan (oklusi, torus, palatum, diastema, anomali) + OHI-S
 * (Greene & Vermillion: skor debris & kalkulus 0–3 pada enam gigi indeks). Terkunci bersama rekam medis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oral_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('occlusion', 20)->nullable();
            $table->string('torus_palatinus', 20)->nullable();
            $table->string('torus_mandibularis', 20)->nullable();
            $table->string('palate', 20)->nullable();
            $table->string('diastema')->nullable();
            $table->string('anomaly')->nullable();
            // {"16": {"debris": 1, "calculus": 0}, ...}
            $table->json('ohis_scores')->nullable();
            $table->decimal('debris_index', 3, 2)->nullable();
            $table->decimal('calculus_index', 3, 2)->nullable();
            $table->decimal('ohis', 3, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });

        // Warna kode odontogram mengikuti mockup v2 bila masih warna bawaan lama (komposit tidak lagi biru,
        // supaya tidak tertukar dengan warna "dikerjakan di kunjungan ini").
        foreach ([['cof', '#2563eb', '#12b76a'], ['amf', '#374151', '#344054'], ['car', '#ef4444', '#f79009']] as [$code, $old, $new]) {
            DB::table('odontogram_conditions')->where('code', $code)->where('color', $old)->update(['color' => $new]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oral_examinations');
    }
};
