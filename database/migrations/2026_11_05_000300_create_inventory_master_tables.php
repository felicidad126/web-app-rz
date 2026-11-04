<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 30)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->string('kfa_code', 30)->nullable()->index();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->boolean('track_expiry')->default(true);
            $table->unsignedBigInteger('sell_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            // Jumlah satuan dasar dalam 1 satuan ini. Contoh: 1 box = 100 pcs -> 100.
            $table->decimal('factor_to_base', 12, 4);
            $table->timestamps();

            $table->unique(['item_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_units');
        Schema::dropIfExists('items');
        Schema::dropIfExists('units');
    }
};
