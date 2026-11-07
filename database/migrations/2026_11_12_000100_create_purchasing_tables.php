<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian BHP (alur dari aplikasi inventori KP klinik): permintaan perawat → pesanan ke supplier →
 * penerimaan (lewat StockLedger, bisa sebagian) → kas pembelian per cabang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_at')->nullable();
            $table->string('status', 20)->default('ordered');
            // Rupiah: total pesanan & yang sudah dibayar dari kas pembelian.
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            // Jumlah dalam satuan yang diminta + snapshot dalam satuan dasar.
            $table->decimal('quantity', 14, 3);
            $table->decimal('base_quantity', 14, 3);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            // Snapshot konversi saat pesan: 1 satuan pesan = factor_to_base satuan dasar.
            $table->decimal('factor_to_base', 14, 4)->default(1);
            $table->decimal('quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            // Rupiah per satuan pesan.
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Kas pembelian per cabang: buku besar yang tidak bisa diubah (koreksi lewat entri pembalik).
        Schema::create('purchase_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('entry_date');
            $table->string('type', 20);
            // Rupiah bertanda: + masuk ke kas, − keluar dari kas.
            $table->bigInteger('amount');
            $table->string('description', 255);
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('purchase_cash_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_cash_entries');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('purchase_orders');
    }
};
