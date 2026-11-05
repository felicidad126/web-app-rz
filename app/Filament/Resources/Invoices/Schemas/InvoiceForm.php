<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceItemType;
use App\Enums\PayerType;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payer;
use App\Models\Treatment;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\InvoiceCalculator;
use App\Support\Money;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Form kasir untuk draf tagihan (mengikuti mockup "Kasir"): baris tagihan di kiri, ringkasan di kanan.
 */
class InvoiceForm
{
    public static function configure(Schema $schema, ?Invoice $invoice = null): Schema
    {
        $branchId = $invoice?->branch_id;

        return $schema
            ->columns(1)
            ->components([
                View::make('filament.clinic.patient-banner')
                    ->viewData(['patient' => $invoice?->patient])
                    ->visible($invoice?->patient !== null),
                // Tabel baris butuh lebar ±36rem; ringkasan pindah ke bawah di layar < 1536px (tablet, laptop 13").
                Grid::make(['default' => 1, '2xl' => 3])->schema([
                    Section::make('Tagihan')
                        ->description(fn (): string => self::heading($invoice))
                        ->columnSpan(['2xl' => 2])
                        ->schema([
                            Repeater::make('items')
                                ->hiddenLabel()
                                ->relationship()
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->addActionLabel('Tambah item')
                                ->live()
                                // "10.00" dari kolom desimal ditampilkan "10".
                                ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => [...$data, 'qty' => is_numeric($data['qty'] ?? null) ? $data['qty'] + 0 : 1])
                                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                                    ...$data,
                                    'item_type' => $data['item_type'] ?? InvoiceItemType::Fee->value,
                                    // Baris tambahan manual tampil setelah baris dari kunjungan.
                                    'sort' => 1000,
                                ])
                                ->table([
                                    TableColumn::make('Item')->width('44%'),
                                    TableColumn::make('Jml')->width('10%'),
                                    TableColumn::make('Harga')->width('17%'),
                                    TableColumn::make('Diskon')->width('15%'),
                                    TableColumn::make('Jumlah')->width('14%')->alignEnd(),
                                ])
                                ->schema([
                                    Group::make([
                                        Hidden::make('item_type'),
                                        Hidden::make('treatment_id'),
                                        Hidden::make('item_id'),
                                        Hidden::make('unit_id'),
                                        Hidden::make('visit_procedure_id'),
                                        Hidden::make('prescription_item_id'),
                                        Hidden::make('installment_id'),
                                        Hidden::make('tooth_number'),
                                        Hidden::make('surfaces'),
                                        Select::make('catalog')
                                            ->hiddenLabel()
                                            ->placeholder('Pilih tindakan / obat / biaya lain')
                                            ->options(fn (): array => self::catalog($branchId))
                                            ->searchable()
                                            ->required()
                                            ->dehydrated(false)
                                            ->live()
                                            ->visible(fn (Get $get): bool => blank($get('description')))
                                            ->afterStateUpdated(fn (?string $state, Set $set) => self::applyCatalog($state, $set, $branchId)),
                                        TextInput::make('description')
                                            ->hiddenLabel()
                                            ->required()
                                            ->maxLength(255)
                                            ->readOnly(fn (Get $get): bool => self::isLinked($get))
                                            ->suffix(fn (Get $get): ?string => trim(($get('tooth_number') ?? '').' '.($get('surfaces') ?? '')) ?: null)
                                            ->visible(fn (Get $get): bool => filled($get('description')) || filled($get('item_type'))),
                                    ]),
                                    TextInput::make('qty')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(0.01)
                                        ->default(1)
                                        ->required()
                                        ->readOnly(fn (Get $get): bool => self::isLinked($get))
                                        ->live(onBlur: true),
                                    TextInput::make('unit_price')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->required()
                                        ->readOnly(fn (Get $get): bool => self::isLinked($get))
                                        ->live(onBlur: true),
                                    TextInput::make('item_discount')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->live(onBlur: true),
                                    Text::make(fn (Get $get): string => Money::format(self::lineAmount($get), false))
                                        ->extraAttributes(['style' => 'display: block; text-align: right; font-variant-numeric: tabular-nums; font-weight: 600']),
                                ]),
                        ]),
                    Grid::make(['default' => 1, 'md' => 2, '2xl' => 1])
                        ->columnSpan(1)
                        ->schema([
                            Section::make('Ringkasan')
                                ->schema([
                                    Select::make('payer_id')
                                        ->label('Penjamin')
                                        ->options(fn (): array => Payer::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                                        ->placeholder('Umum (bayar sendiri)')
                                        ->live(),
                                    TextInput::make('payer_portion')
                                        ->label('Ditanggung penjamin')
                                        ->prefix('Rp')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->visible(fn (Get $get): bool => self::isInsured($get('payer_id'))),
                                    Grid::make(2)->schema([
                                        TextInput::make('invoice_discount')
                                            ->label('Diskon tagihan')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->disabled(fn (Get $get): bool => filled($get('invoice_discount_percent'))),
                                        TextInput::make('invoice_discount_percent')
                                            ->label('atau %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->live(onBlur: true),
                                    ]),
                                    Text::make(fn (Get $get): HtmlString => self::summary($get))->extraAttributes(['style' => 'display: block; width: 100%'])->columnSpanFull(),
                                    Textarea::make('notes')->label('Catatan')->rows(2),
                                ]),
                            View::make('filament.billing.checkout-side')
                                ->viewData(['invoice' => $invoice])
                                ->visible($invoice !== null),
                        ]),
                ]),
            ]);
    }

    private static function heading(?Invoice $invoice): string
    {
        if ($invoice === null) {
            return '';
        }

        $visit = $invoice->visit;

        return $visit
            ? 'Kunjungan '.$visit->visit_number.' · '.$visit->practitioner->user->name.' · '.$visit->visit_date->format('d/m/Y')
            : 'Penjualan langsung';
    }

    /** @return array<string, array<string, string>> */
    private static function catalog(?int $branchId): array
    {
        $treatments = Treatment::query()->where('is_active', true)->with('branchPrices')->orderBy('name')->get()
            ->mapWithKeys(fn (Treatment $t): array => ['t:'.$t->id => $t->name.' · '.Money::format($t->priceForBranch($branchId))])
            ->all();

        $items = Item::query()->where('is_active', true)->whereNotNull('sell_price')->with('baseUnit')->orderBy('name')->get()
            ->mapWithKeys(fn (Item $i): array => ['i:'.$i->id => $i->name.' · '.Money::format($i->sell_price).' / '.$i->baseUnit->name])
            ->all();

        return [
            'Tindakan' => $treatments,
            'Obat & bahan' => $items,
            'Lainnya' => ['fee' => 'Biaya lain (isi nama & harga sendiri)'],
        ];
    }

    private static function applyCatalog(?string $choice, Set $set, ?int $branchId): void
    {
        if ($choice === null) {
            return;
        }

        $set('qty', 1);
        $set('item_discount', 0);

        if ($choice === 'fee') {
            $set('item_type', InvoiceItemType::Fee->value);
            $set('description', 'Biaya lain');
            $set('unit_price', 0);

            return;
        }

        [$kind, $id] = explode(':', $choice);

        if ($kind === 't' && ($treatment = Treatment::find($id))) {
            $set('item_type', InvoiceItemType::Procedure->value);
            $set('treatment_id', $treatment->id);
            $set('description', $treatment->name);
            $set('unit_price', $treatment->priceForBranch($branchId));
        }

        if ($kind === 'i' && ($item = Item::find($id))) {
            $set('item_type', InvoiceItemType::Item->value);
            $set('item_id', $item->id);
            $set('unit_id', $item->base_unit_id);
            $set('description', $item->name);
            $set('unit_price', InvoiceBuilder::itemUnitPrice($item, $item->base_unit_id));
        }
    }

    /** Baris dari tindakan/resep/cicilan: nama, jumlah, dan harga mengikuti sumbernya. */
    private static function isLinked(Get $get): bool
    {
        return filled($get('visit_procedure_id')) || filled($get('prescription_item_id')) || filled($get('installment_id'));
    }

    private static function isInsured(mixed $payerId): bool
    {
        return filled($payerId) && Payer::find($payerId)?->type !== PayerType::General;
    }

    private static function lineAmount(Get $get): int
    {
        $result = InvoiceCalculator::calculate([self::lineInput($get('qty'), $get('unit_price'), $get('item_discount'), null)]);

        return $result['lines'][0]['subtotal'];
    }

    /** @return array{qty: float, unit_price: int, item_discount: int, item_discount_percent: string|null} */
    private static function lineInput(mixed $qty, mixed $price, mixed $discount, mixed $percent): array
    {
        return [
            'qty' => is_numeric($qty) ? (float) $qty : 0.0,
            'unit_price' => is_numeric($price) ? (int) $price : 0,
            'item_discount' => is_numeric($discount) ? (int) $discount : 0,
            'item_discount_percent' => is_numeric($percent) ? (string) $percent : null,
        ];
    }

    private static function summary(Get $get): HtmlString
    {
        $items = is_array($get('items')) ? $get('items') : [];
        $lines = array_values(array_map(fn (array $row): array => self::lineInput(
            $row['qty'] ?? 0, $row['unit_price'] ?? 0, $row['item_discount'] ?? 0, null,
        ), $items));

        $result = InvoiceCalculator::calculate(
            $lines,
            is_numeric($get('invoice_discount')) ? (int) $get('invoice_discount') : 0,
            is_numeric($get('invoice_discount_percent')) ? (string) $get('invoice_discount_percent') : null,
            (int) config('clinic.billing.rounding', 0),
            self::isInsured($get('payer_id')) && is_numeric($get('payer_portion')) ? (int) $get('payer_portion') : 0,
        );

        $rows = [
            ['Subtotal', $result['subtotal'], false],
            ['Diskon tagihan', -$result['invoice_discount'], false],
        ];

        if ($result['tax_total'] > 0) {
            $rows[] = ['Pajak', $result['tax_total'], false];
        }

        if ($result['rounding'] !== 0) {
            $rows[] = ['Pembulatan', $result['rounding'], false];
        }

        $rows[] = ['Total', $result['total'], true];

        if ($result['payer_portion'] > 0) {
            $rows[] = ['Ditanggung penjamin', -$result['payer_portion'], false];
            $rows[] = ['Dibayar pasien', $result['patient_portion'], true];
        }

        $html = '<dl style="display: grid; grid-template-columns: 1fr auto; gap: 6px 12px; font-size: 14px; margin: 0; width: 100%">';

        foreach ($rows as [$label, $amount, $strong]) {
            $style = $strong ? 'font-weight: 600; border-top: 1px solid #e4e4e7; padding-top: 6px' : 'color: #52525b';
            $html .= '<dt style="'.$style.'">'.e($label).'</dt><dd style="margin: 0; text-align: right; font-variant-numeric: tabular-nums; '.$style.'">'.e(Money::format($amount, false)).'</dd>';
        }

        return new HtmlString($html.'</dl>');
    }
}
