<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('satusehat_location_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'user_id']);
        });

        Schema::create('practitioners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('specialty')->nullable();
            $table->text('nik')->nullable();
            $table->string('nik_hash', 64)->nullable()->unique();
            $table->string('str_number')->nullable();
            $table->string('sip_number')->nullable();
            $table->date('sip_expired_at')->nullable()->index();
            $table->string('satusehat_ihs_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('scope', 50)->default('');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('practitioners');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('chairs');
        Schema::dropIfExists('branches');
    }
};
