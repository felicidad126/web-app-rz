<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5: stok per batch (FEFO), mutasi berdokumen, stok opname, BOM tindakan, supplier, order lab.
 *
 * Jumlah stok selalu dalam SATUAN DASAR barang (decimal, mis. 0,5 ml). Harga pokok per satuan dasar
 * dalam rupiah (bigint). Saldo batch (`item_batches.qty_on_hand`) hanya cache dari baris mutasi yang
 * sudah tercatat, diperbarui dalam transaksi database yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('item_category_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Lab gigi (crown, gigi tiruan, retainer) juga dicatat sebagai pemasok.
            $table->boolean('is_lab')->default(false);
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // BOM: bahan yang dipakai per 1× tindakan.
        Schema::create('treatment_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['treatment_id', 'item_id']);
        });

        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 60)->nullable();
            $table->date('expired_at')->nullable();
            // Harga pokok per satuan dasar (rupiah).
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->decimal('qty_on_hand', 14, 3)->default(0);
            $table->date('received_at')->nullable();
            // Penampung "tanpa batch": pemakaian saat stok tercatat habis (bisa minus sampai opname).
            $table->boolean('is_unbatched')->default(false);
            $table->timestamps();

            $table->index(['item_id', 'branch_id', 'expired_at']);
            $table->index(['branch_id', 'expired_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->date('movement_date');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->foreignId('counterpart_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            // Dokumen asal: kunjungan (pemakaian), resep, tagihan (penjualan), stok opname.
            $table->nullableMorphs('source');
            $table->string('reason', 20)->nullable();
            $table->text('notes')->nullable();
            // Nilai mutasi (harga pokok), bertanda: + masuk, − keluar.
            $table->bigInteger('total_cost')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'movement_date']);
            $table->index(['status', 'type']);
        });

        Schema::create('stock_movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained()->restrictOnDelete();
            // Rincian sumber untuk HPP per tindakan.
            $table->foreignId('visit_procedure_id')->nullable()->constrained()->nullOnDelete();
            // Seperti yang diketik petugas (mis. 2 box), dan hasil konversi ke satuan dasar (bertanda).
            $table->decimal('entered_qty', 14, 3);
            $table->foreignId('entered_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->bigInteger('line_cost')->default(0);
            // Untuk penerimaan/draf: data batch sebelum batch dibuat.
            $table->string('batch_number', 60)->nullable();
            $table->date('expired_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['item_batch_id', 'stock_movement_id']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('counting');
            $table->timestamp('started_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('stock_opname_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            // Kosong = batch baru yang ditemukan saat hitung (dibuat saat disetujui).
            $table->foreignId('item_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('batch_number', 60)->nullable();
            $table->date('expired_at')->nullable();
            $table->unsignedBigInteger('unit_cost')->default(0);
            // Snapshot saat opname dimulai (satuan dasar).
            $table->decimal('system_qty', 14, 3)->default(0);
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('work_type', 20);
            $table->string('specification');
            $table->string('teeth', 120)->nullable();
            $table->string('shade', 20)->nullable();
            $table->unsignedBigInteger('cost')->default(0);
            $table->string('status', 20)->default('created');
            $table->date('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('fitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('stock_opname_lines');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_movement_lines');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('item_batches');
        Schema::dropIfExists('treatment_materials');
        Schema::dropIfExists('suppliers');
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_category_id');
        });
        Schema::dropIfExists('item_categories');
    }
};
