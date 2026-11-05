<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->json('vitals')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Koreksi setelah rekam medis dikunci. Tidak bisa diubah/dihapus.
        Schema::create('medical_record_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->restrictOnDelete();
            $table->text('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->string('icd10_code', 10);
            $table->string('type', 20)->default('primary');
            $table->string('tooth_number', 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('icd10_code')->references('code')->on('icd10_codes')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('presented_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->restrictOnDelete();
            $table->string('tooth_number', 2)->nullable();
            $table->string('surfaces', 5)->nullable();
            $table->unsignedSmallInteger('phase')->default(1);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->unsignedBigInteger('price_estimate')->default(0);
            $table->string('status', 20)->default('planned');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('visit_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_id')->constrained()->restrictOnDelete();
            $table->string('icd9cm_code', 10)->nullable();
            $table->string('tooth_number', 2)->nullable();
            $table->string('surfaces', 5)->nullable();
            $table->foreignId('operator_id')->constrained('practitioners')->restrictOnDelete();
            $table->foreignId('assistant_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedBigInteger('price_snapshot');
            $table->foreignId('treatment_plan_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('odontogram_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tooth_number', 2);
            $table->char('surface', 1)->nullable();
            $table->string('condition_code', 10);
            $table->string('entry_type', 10);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['patient_id', 'tooth_number']);
            $table->foreign('condition_code')->references('code')->on('odontogram_conditions')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('kfa_code', 30)->nullable();
            $table->decimal('qty', 10, 2);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('signa');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->date('taken_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('informed_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('consent_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->longText('content_snapshot');
            $table->string('signer_name');
            $table->string('signer_relation', 30);
            $table->string('signature_path');
            $table->string('pdf_path')->nullable();
            $table->timestamp('signed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->date('due_date')->index();
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_contacted_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Interval recall otomatis per tindakan (mis. scaling: 6 bulan).
        Schema::table('treatments', function (Blueprint $table) {
            $table->unsignedSmallInteger('recall_months')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('recall_months');
        });
        Schema::dropIfExists('recalls');
        Schema::dropIfExists('informed_consents');
        Schema::dropIfExists('consent_templates');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('odontogram_entries');
        Schema::dropIfExists('visit_procedures');
        Schema::dropIfExists('treatment_plan_items');
        Schema::dropIfExists('treatment_plans');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('medical_record_addenda');
        Schema::dropIfExists('medical_records');
    }
};
