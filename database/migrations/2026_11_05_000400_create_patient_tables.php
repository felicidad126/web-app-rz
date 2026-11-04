<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('rm_number', 30)->unique();
            $table->string('name')->index();
            $table->text('nik')->nullable();
            $table->string('nik_hash', 64)->nullable()->unique();
            $table->string('satusehat_ihs_id')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date');
            $table->string('gender', 10);
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('blood_type', 3)->nullable();
            $table->text('allergies')->nullable();
            $table->text('systemic_diseases')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->foreignId('default_payer_id')->nullable()->constrained('payers')->nullOnDelete();
            $table->foreignId('registered_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('consent_data_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_payers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained()->restrictOnDelete();
            $table->string('member_number')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'payer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_payers');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('payers');
    }
};
