<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: status pengiriman ke SATUSEHAT.
 *
 * Satu baris per data yang dikirim (saat ini: satu kunjungan = satu Bundle berisi Encounter, Condition,
 * Observation, Procedure, Medication, MedicationRequest). ID dari SATUSEHAT disimpan per resource.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satusehat_syncs', function (Blueprint $table) {
            $table->id();
            $table->morphs('syncable');
            $table->string('resource_type', 40);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            // ID resource utama (mis. Encounter) dan semua ID resource yang ikut terkirim.
            $table->string('satusehat_id', 64)->nullable();
            $table->json('resource_ids')->nullable();
            // Jumlah resource per jenis yang akan/ sudah dikirim, untuk rekap per jenis data.
            $table->json('resource_counts')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Kode masalah (mis. patient_nik_missing) untuk panduan perbaikan di layar.
            $table->string('issue_code', 40)->nullable();
            $table->text('last_error')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['syncable_type', 'syncable_id', 'resource_type']);
            $table->index(['status', 'updated_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('satusehat_syncs');
    }
};
