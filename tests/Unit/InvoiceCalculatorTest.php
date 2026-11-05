<?php

use App\Services\Billing\InvoiceCalculator;

$visitLines = [
    ['qty' => 1, 'unit_price' => 100_000],
    ['qty' => 1, 'unit_price' => 400_000],
    ['qty' => 1, 'unit_price' => 400_000],
    ['qty' => '10.00', 'unit_price' => 1_500],
];

it('menjumlahkan baris tanpa diskon seperti di mockup kasir', function () use ($visitLines) {
    $result = InvoiceCalculator::calculate($visitLines);

    expect($result)->toMatchArray([
        'subtotal' => 915_000, 'invoice_discount' => 0, 'tax_total' => 0, 'rounding' => 0,
        'total' => 915_000, 'payer_portion' => 0, 'patient_portion' => 915_000,
    ])->and(array_column($result['lines'], 'net_amount'))->toBe([100_000, 400_000, 400_000, 15_000]);
});

it('menerapkan diskon item nominal & persen, dibatasi maksimal sebesar baris', function () {
    $result = InvoiceCalculator::calculate([
        ['qty' => 2, 'unit_price' => 150_000, 'item_discount' => 50_000],
        ['qty' => 1, 'unit_price' => 400_000, 'item_discount_percent' => '12.5'],
        ['qty' => 1, 'unit_price' => 20_000, 'item_discount' => 99_000],
    ]);

    expect(array_column($result['lines'], 'item_discount'))->toBe([50_000, 50_000, 20_000])
        ->and(array_column($result['lines'], 'subtotal'))->toBe([250_000, 350_000, 0])
        ->and($result['total'])->toBe(600_000);
});

it('mengalokasikan diskon invoice pro-rata dan jumlahnya selalu pas', function () use ($visitLines) {
    $result = InvoiceCalculator::calculate($visitLines, invoiceDiscount: 100_000);
    $allocated = array_column($result['lines'], 'allocated_invoice_discount');

    expect($allocated)->toBe([10_929, 43_716, 43_716, 1_639])
        ->and(array_sum($allocated))->toBe(100_000)
        ->and($result['total'])->toBe(815_000)
        ->and(array_sum(array_column($result['lines'], 'net_amount')))->toBe(815_000);
});

it('menghitung diskon invoice persen dari subtotal setelah diskon item', function () {
    $result = InvoiceCalculator::calculate([
        ['qty' => 1, 'unit_price' => 300_000, 'item_discount' => 100_000],
        ['qty' => 1, 'unit_price' => 300_000],
    ], invoiceDiscountPercent: 10);

    expect($result['invoice_discount'])->toBe(50_000)
        ->and(array_column($result['lines'], 'allocated_invoice_discount'))->toBe([20_000, 30_000])
        ->and($result['total'])->toBe(450_000);
});

it('menghitung pajak per baris dari neto, lalu pembulatan total', function () {
    $result = InvoiceCalculator::calculate([
        ['qty' => 1, 'unit_price' => 400_000],
        ['qty' => 3, 'unit_price' => 33_333, 'tax_rate' => 11],
    ], invoiceDiscount: 10_000, roundingUnit: 1_000);

    // Baris 2: 99.999 − alokasi 2.000 = 97.999; PPN 11% = 10.779,89 → 10.780.
    expect(array_column($result['lines'], 'allocated_invoice_discount'))->toBe([8_000, 2_000])
        ->and($result['lines'][1]['tax'])->toBe(10_780)
        ->and($result['tax_total'])->toBe(10_780)
        ->and($result['rounding'])->toBe(221)
        ->and($result['total'])->toBe(501_000);
});

it('membatasi porsi penjamin ke total dan diskon ke subtotal', function () {
    $result = InvoiceCalculator::calculate([['qty' => 1, 'unit_price' => 200_000]], invoiceDiscount: 500_000, payerPortion: 50_000);

    expect($result['invoice_discount'])->toBe(200_000)
        ->and($result['total'])->toBe(0)
        ->and($result['payer_portion'])->toBe(0)
        ->and($result['patient_portion'])->toBe(0);

    $covered = InvoiceCalculator::calculate([['qty' => 1, 'unit_price' => 200_000]], payerPortion: 150_000);
    expect($covered['payer_portion'])->toBe(150_000)->and($covered['patient_portion'])->toBe(50_000);
});

it('membagi sisa alokasi ke baris dengan pecahan terbesar; seri ke baris pertama', function () {
    expect(InvoiceCalculator::allocate(100, [1, 1, 1]))->toBe([34, 33, 33])
        ->and(InvoiceCalculator::allocate(10, [0, 5, 0]))->toBe([0, 10, 0])
        ->and(InvoiceCalculator::allocate(0, [3, 4]))->toBe([0, 0])
        ->and(InvoiceCalculator::allocate(7, []))->toBe([]);
});
