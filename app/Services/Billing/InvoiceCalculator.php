<?php

namespace App\Services\Billing;

/**
 * Rumus tagihan (Planning v2 §5.4). Murni: tidak membaca database, mudah diuji.
 *
 *   subtotal_item = qty × harga_satuan − diskon_item
 *   subtotal      = Σ subtotal_item
 *   total         = subtotal − diskon_invoice + pajak + pembulatan
 *   porsi_pasien  = total − porsi_penjamin
 *
 * Diskon invoice dialokasikan pro-rata ke setiap baris (metode sisa terbesar, jumlahnya selalu pas)
 * supaya neto per baris bisa dipakai untuk komisi & margin. Pajak dihitung dari neto per baris.
 */
final class InvoiceCalculator
{
    /**
     * @param  list<array{qty: float|int|string, unit_price: int, item_discount?: int|null, item_discount_percent?: float|int|string|null, tax_rate?: float|int|string|null}>  $lines
     * @param  int  $roundingUnit  0 = tanpa pembulatan, 100, 1000, ...
     * @return array{
     *     lines: list<array{gross: int, item_discount: int, subtotal: int, allocated_invoice_discount: int, net_amount: int, tax: int}>,
     *     subtotal: int, invoice_discount: int, tax_total: int, rounding: int, total: int, payer_portion: int, patient_portion: int
     * }
     */
    public static function calculate(
        array $lines,
        int $invoiceDiscount = 0,
        float|int|string|null $invoiceDiscountPercent = null,
        int $roundingUnit = 0,
        int $payerPortion = 0,
    ): array {
        $computed = [];

        foreach ($lines as $line) {
            $gross = self::round((float) $line['qty'] * $line['unit_price']);
            $percent = $line['item_discount_percent'] ?? null;
            $discount = filled($percent)
                ? self::round($gross * (float) $percent / 100)
                : (int) ($line['item_discount'] ?? 0);
            $discount = max(0, min($discount, $gross));

            $computed[] = ['gross' => $gross, 'item_discount' => $discount, 'subtotal' => $gross - $discount, 'tax_rate' => (float) ($line['tax_rate'] ?? 0)];
        }

        $subtotal = array_sum(array_column($computed, 'subtotal'));
        $discount = filled($invoiceDiscountPercent)
            ? self::round($subtotal * (float) $invoiceDiscountPercent / 100)
            : $invoiceDiscount;
        $discount = max(0, min($discount, $subtotal));

        $allocations = self::allocate($discount, array_column($computed, 'subtotal'));
        $result = [];
        $taxTotal = 0;

        foreach ($computed as $index => $line) {
            $net = $line['subtotal'] - $allocations[$index];
            $tax = self::round($net * $line['tax_rate'] / 100);
            $taxTotal += $tax;

            $result[] = [
                'gross' => $line['gross'],
                'item_discount' => $line['item_discount'],
                'subtotal' => $line['subtotal'],
                'allocated_invoice_discount' => $allocations[$index],
                'net_amount' => $net,
                'tax' => $tax,
            ];
        }

        $beforeRounding = $subtotal - $discount + $taxTotal;
        $rounding = $roundingUnit > 0 ? self::round($beforeRounding / $roundingUnit) * $roundingUnit - $beforeRounding : 0;
        $total = max(0, $beforeRounding + $rounding);
        $payerPortion = max(0, min($payerPortion, $total));

        return [
            'lines' => $result,
            'subtotal' => $subtotal,
            'invoice_discount' => $discount,
            'tax_total' => $taxTotal,
            'rounding' => $total - $beforeRounding,
            'total' => $total,
            'payer_portion' => $payerPortion,
            'patient_portion' => $total - $payerPortion,
        ];
    }

    /**
     * Bagi $amount secara proporsional ke $weights; jumlah hasil selalu = $amount.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($amount <= 0 || $total <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $exact = intdiv($amount * $weight, $total);
            $shares[$index] = $exact;
            $remainders[$index] = ($amount * $weight) % $total;
        }

        $left = $amount - array_sum($shares);

        // Sisa dibagikan ke baris dengan pecahan terbesar; seri → baris yang lebih awal.
        $order = array_keys($remainders);
        usort($order, fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);

        foreach (array_slice($order, 0, $left) as $index) {
            $shares[$index]++;
        }

        return array_values($shares);
    }

    /** Pembulatan setengah ke atas (menjauhi nol), hasil integer rupiah. */
    private static function round(float $value): int
    {
        return (int) round($value, 0, PHP_ROUND_HALF_UP);
    }
}
