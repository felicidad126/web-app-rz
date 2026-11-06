<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6: jasa dokter (komisi), kehadiran dokter, rekap & slip, gaji staf, snapshot KPI harian, dan target.
 *
 * Uang dalam rupiah (bigint). Entri jasa tidak diubah setelah dibuat: pembatalan/refund membuat entri pembalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Jenis jasa (kelompok bagi hasil), terpisah dari kategori klinis tindakan.
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('fee_group', 20)->default('general')->after('category');
        });

        DB::table('treatments')->where('category', 'consultation')->update(['fee_group' => 'consultation']);
        DB::table('treatments')->where('category', 'prosthodontic')->update(['fee_group' => 'lab_work']);
        DB::table('treatments')->where('category', 'orthodontic')->update(['fee_group' => 'orthodontic']);

        // Uang hadir & on call per dokter (keputusan owner: ada uang hadir / on call).
        Schema::table('practitioners', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_fee')->default(0);
            $table->unsignedBigInteger('on_call_fee')->default(0);
        });

        // Nilai paket yang terpakai pada baris tindakan bertarif 0 (kontrol ortho, dll.), untuk produksi & jasa.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('package_value')->default(0)->after('net_amount');
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_group', 20)->nullable();
            $table->string('role', 20)->default('operator');
            $table->string('type', 10)->default('percent');
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->string('base', 40)->default('net');
            $table->string('recognition', 20)->default('performed');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'is_active']);
        });

        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->string('status', 20)->default('draft');
            $table->bigInteger('commission_total')->default(0);
            $table->bigInteger('allowance_total')->default(0);
            $table->bigInteger('adjustment_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['practitioner_id', 'period']);
            $table->index(['period', 'status']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rule_snapshot');
            $table->date('service_date');
            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('material_cost')->default(0);
            $table->bigInteger('lab_cost')->default(0);
            $table->bigInteger('base_amount')->default(0);
            // Bertanda: entri pembalik bernilai negatif.
            $table->bigInteger('amount');
            $table->string('status', 20);
            $table->timestamp('earned_at')->nullable();
            $table->foreignId('reverses_commission_id')->nullable()->constrained('commissions')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('commission_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['practitioner_id', 'status', 'earned_at']);
            $table->index(['invoice_id']);
            $table->index(['service_date', 'branch_id']);
        });

        Schema::create('practitioner_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('notes')->nullable();
            $table->foreignId('commission_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['practitioner_id', 'branch_id', 'date', 'type'], 'attendance_unique');
        });

        Schema::create('commission_payout_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_payout_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('kind', 20);
            $table->unsignedBigInteger('default_amount')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Komponen gaji tetap per karyawan (template slip bulanan).
        Schema::create('employee_salary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['user_id', 'salary_component_id']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('earning_total')->default(0);
            $table->unsignedBigInteger('deduction_total')->default(0);
            $table->bigInteger('net_total')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('name', 80);
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Snapshot KPI harian (diisi job 00:30) agar angka kemarin tidak berubah dan dashboard cepat.
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric', 40);
            $table->bigInteger('value')->default(0);
            $table->timestamps();

            $table->index(['date', 'branch_id', 'metric']);
            $table->index(['practitioner_id', 'metric', 'date']);
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric', 40)->default('production');
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('employee_salary_items');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('commission_payout_adjustments');
        Schema::dropIfExists('practitioner_attendances');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_rules');
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('package_value'));
        Schema::table('practitioners', fn (Blueprint $table) => $table->dropColumn(['attendance_fee', 'on_call_fee']));
        Schema::table('treatments', fn (Blueprint $table) => $table->dropColumn('fee_group'));
    }
};
