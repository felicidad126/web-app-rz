<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3: kasir & billing.
 *
 * Semua uang dalam rupiah (bigint). Tidak ada hapus: tagihan & pembayaran dibatalkan lewat status void
 * dengan alasan dan persetujuan owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            // Snapshot saat ditutup: kas awal + tunai masuk − tunai keluar.
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->json('denominations')->nullable();
            // Snapshot per metode: {method: {system, reported, matched}}.
            $table->json('method_totals')->nullable();
            $table->string('difference_reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('deposited_to_safe')->nullable();
            $table->unsignedBigInteger('next_opening_cash')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'opened_at']);
            $table->index(['user_id', 'closed_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Nomor diberikan saat diterbitkan agar draf yang batal tidak membuat nomor bolong.
            $table->string('invoice_number', 30)->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('invoice_discount')->default(0);
            $table->decimal('invoice_discount_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->integer('rounding')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('payer_portion')->default(0);
            $table->unsignedBigInteger('patient_portion')->default(0);
            // Σ pembayaran aktif (termasuk deposit) − Σ refund yang sudah diserahkan.
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('void_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'issued_at']);
            $table->index(['patient_id', 'status']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quota');
            $table->timestamps();

            $table->unique(['package_id', 'treatment_id']);
        });

        Schema::create('patient_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('down_payment')->default(0);
            $table->unsignedSmallInteger('installment_count')->default(0);
            $table->date('start_date');
            $table->unsignedTinyInteger('due_day');
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
        });

        // Kuota di-snapshot saat paket dijual; perubahan master paket tidak mengubah paket pasien.
        Schema::create('patient_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quota');
            $table->timestamps();

            $table->unique(['patient_package_id', 'treatment_id']);
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            // 0 = uang muka (DP).
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_package_id', 'sequence']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('visit_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('tooth_number', 2)->nullable();
            $table->string('surfaces', 5)->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('item_discount')->default(0);
            $table->decimal('item_discount_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('allocated_invoice_discount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            // Setelah diskon item & alokasi diskon invoice, sebelum pajak (basis komisi "neto").
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assistant_id')->nullable()->constrained('practitioners')->nullOnDelete();
            // Diisi Fase 5 (HPP bahan, biaya lab) dan Fase 6 (aturan komisi).
            $table->unsignedBigInteger('material_cost')->default(0);
            $table->unsignedBigInteger('lab_cost')->default(0);
            $table->json('commission_rule_snapshot')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('package_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Satu kali "Terima pembayaran" (satu struk) bisa melunasi beberapa tagihan.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('cash_tendered')->nullable();
            $table->unsignedBigInteger('change_given')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'paid_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            $table->string('reference_no', 60)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('void_reason')->nullable();
            $table->foreignId('void_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
        });

        Schema::create('deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            // Bertanda: + menambah saldo, − mengurangi.
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('method', 20)->nullable();
            $table->foreignId('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_transactions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('package_usages');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('patient_package_items');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cash_sessions');
    }
};
