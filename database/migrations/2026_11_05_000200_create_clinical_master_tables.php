<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('name_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('icd9cm_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('name_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('odontogram_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('category', 20);
            $table->string('applies_to', 10);
            $table->string('color', 7)->default('#6b7280');
            $table->string('symbol', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 30);
            $table->string('icd9cm_code', 10)->nullable();
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('icd9cm_code')->references('code')->on('icd9cm_codes')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('treatment_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(['treatment_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_branch_prices');
        Schema::dropIfExists('treatments');
        Schema::dropIfExists('odontogram_conditions');
        Schema::dropIfExists('icd9cm_codes');
        Schema::dropIfExists('icd10_codes');
    }
};
