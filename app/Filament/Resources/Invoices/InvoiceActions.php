<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundSource;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentRecorder;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Aksi kasir yang dipakai di halaman draf (kasir) dan detail tagihan.
 */
final class InvoiceActions
{
    public static function receivePayment(): Action
    {
        return Action::make('receivePayment')
            // Angka draf baru pasti setelah disimpan (dilakukan saat tombol diklik), jadi label draf tanpa nominal.
            ->label(fn (Invoice $record): string => $record->isDraft() ? 'Terima pembayaran' : 'Terima pembayaran '.Money::format(self::defaultDue($record)))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->size('lg')
            ->visible(fn (Invoice $record): bool => $record->void_requested_at === null && ($record->isDraft() || ($record->isOutstanding() && self::defaultDue($record) > 0)))
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->modalHeading('Terima pembayaran')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Simpan pembayaran')
            ->fillForm(fn (Invoice $record): array => self::paymentFormData($record))
            ->schema(fn (Invoice $record): array => [
                Section::make('Sesi kasir belum dibuka')
                    ->description('Pembayaran dicatat ke sesi kasir Anda. Isi kas awal di laci untuk membuka sesi.')
                    ->visible(fn (): bool => self::session($record) === null)
                    ->schema([TextInput::make('opening_cash')->label('Kas awal')->prefix('Rp')->numeric()->minValue(0)->required()]),
                Text::make(fn (): string => 'Tagihan ini: '.$record->label().' · '.Money::format($record->balance())),
                CheckboxList::make('invoice_ids')
                    ->label('Tagih sekalian (saldo terutang)')
                    ->options(fn (): array => CheckoutPanel::otherOutstanding($record)->mapWithKeys(fn (Invoice $invoice): array => [
                        (string) $invoice->id => $invoice->label().' · '.$invoice->issued_at?->format('d/m/Y').' · sisa '.Money::format($invoice->balance()),
                    ])->all())
                    ->helperText('Dialokasikan ke tagihan terlama dulu.')
                    ->visible(fn (): bool => CheckoutPanel::otherOutstanding($record)->isNotEmpty())
                    ->live(),
                Text::make(fn (Get $get): string => 'Harus dibayar: '.Money::format(self::dueFor($record, $get('invoice_ids'))))
                    ->weight('bold'),
                Repeater::make('payments')
                    ->label('Pembayaran')
                    ->addActionLabel('Tambah metode')
                    ->columns(12)
                    ->minItems(1)
                    ->live()
                    ->schema([
                        Select::make('method')
                            ->label('Metode')
                            ->options(fn (): array => self::methodOptions($record))
                            ->required()
                            ->live()
                            ->columnSpan(4),
                        TextInput::make('amount')->label('Jumlah')->prefix('Rp')->numeric()->minValue(1)->required()->live(onBlur: true)->columnSpan(4),
                        TextInput::make('reference_no')
                            ->label('No. referensi')
                            ->placeholder('Opsional')
                            ->maxLength(60)
                            ->visible(fn (Get $get): bool => ! in_array($get('method'), [PaymentMethod::Cash->value, PaymentMethod::Deposit->value, null], true))
                            ->columnSpan(4),
                    ]),
                TextInput::make('cash_tendered')
                    ->label('Uang tunai diterima')
                    ->prefix('Rp')
                    ->numeric()
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): ?string => self::changeHint($get))
                    ->visible(fn (Get $get): bool => self::cashTotal($get('payments')) > 0),
            ])
            ->action(function (Invoice $record, array $data, Action $action): void {
                /** @var User $user */
                $user = auth()->user();

                // Seluruhnya ditanggung paket/penjamin: cukup diterbitkan, tidak ada uang yang diterima.
                if ($record->isDraft() && self::dueFor($record, $data['invoice_ids'] ?? []) === 0) {
                    InvoiceBuilder::issue($record, $user);
                    Notification::make()->title('Tagihan diterbitkan tanpa pembayaran (Rp0 untuk pasien)')->success()->send();
                    $action->redirect(InvoiceResource::getUrl('view', ['record' => $record]));

                    return;
                }

                try {
                    $session = self::session($record) ?? CashSessions::open($user, $record->branch_id, (int) ($data['opening_cash'] ?? 0));
                    $ids = collect($data['invoice_ids'] ?? [])->map(fn ($id): int => (int) $id)->push($record->id)->unique()->values()->all();
                    $transaction = PaymentRecorder::receive(
                        $record->patient,
                        $ids,
                        array_values($data['payments'] ?? []),
                        $session,
                        $user,
                        filled($data['cash_tendered'] ?? null) ? (int) $data['cash_tendered'] : null,
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Pembayaran belum disimpan')->body($e->getMessage())->danger()->send();

                    throw new Halt;
                }

                Notification::make()
                    ->title('Pembayaran '.Money::format($transaction->total).' tersimpan')
                    ->body($transaction->change_given ? 'Kembalian '.Money::format($transaction->change_given) : null)
                    ->success()
                    ->actions([
                        Action::make('receipt')->label('Cetak struk')->url(route('billing.receipt', $transaction), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();

                $action->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
            });
    }

    public static function issue(): Action
    {
        return Action::make('issue')
            ->label('Terbitkan, bayar nanti')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => $record->isDraft())
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->requiresConfirmation()
            ->modalDescription('Tagihan diberi nomor dan dikunci. Pasien membayar belakangan (masuk saldo terutang).')
            ->action(function (Invoice $record, Action $action): void {
                try {
                    InvoiceBuilder::issue($record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Tagihan belum bisa diterbitkan')->body($e->getMessage())->danger()->send();

                    return;
                }

                $action->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
            });
    }

    public static function refreshFromVisit(): Action
    {
        return Action::make('refreshFromVisit')
            ->label('Muat ulang dari kunjungan')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => $record->isDraft() && $record->visit_id !== null)
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->requiresConfirmation()
            ->modalDescription('Baris tindakan & resep diambil ulang dari rekam medis. Item tambahan yang Anda isi sendiri tetap.')
            ->action(function (Invoice $record, Action $action): void {
                InvoiceBuilder::refreshFromVisit($record);
                $action->redirect(InvoiceResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function requestVoid(): Action
    {
        return Action::make('requestVoid')
            ->label(fn (Invoice $record): string => $record->isDraft() ? 'Batalkan draf' : 'Ajukan void tagihan')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Void && $record->void_requested_at === null)
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->modalDescription(fn (Invoice $record): string => $record->isDraft()
                ? 'Draf dibatalkan tanpa persetujuan karena belum bernomor dan belum ada uang.'
                : 'Void perlu persetujuan owner. Tagihan yang masih punya pembayaran harus di-void pembayarannya (salah input) atau direfund dulu.')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(5)->rows(2)])
            ->action(function (Invoice $record, array $data, Action $action): void {
                /** @var User $user */
                $user = auth()->user();

                try {
                    BillingApprovals::requestInvoiceVoid($record, $data['reason'], $user);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Belum bisa di-void')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($record->fresh()?->status === InvoiceStatus::Void ? 'Draf dibatalkan' : 'Pengajuan void dikirim ke owner')->success()->send();
                $action->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
            });
    }

    public static function requestRefund(): Action
    {
        return Action::make('requestRefund')
            ->label('Ajukan refund')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => BillingApprovals::refundableFromInvoice($record) > 0 && $record->status !== InvoiceStatus::Void)
            ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
            ->modalDescription('Uang dikembalikan ke pasien setelah disetujui owner. Kasir menyerahkannya dari sesi kasir yang terbuka.')
            ->schema(fn (Invoice $record): array => [
                TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->minValue(1)->maxValue(BillingApprovals::refundableFromInvoice($record))->required()
                    ->helperText('Maksimal '.Money::format(BillingApprovals::refundableFromInvoice($record))),
                Select::make('method')->label('Dikembalikan lewat')->options([
                    PaymentMethod::Cash->value => 'Tunai',
                    PaymentMethod::Transfer->value => 'Transfer',
                    PaymentMethod::Deposit->value => 'Deposit pasien',
                ])->default(PaymentMethod::Cash->value)->required(),
                Textarea::make('reason')->label('Alasan')->required()->minLength(5)->rows(2),
            ])
            ->action(function (Invoice $record, array $data): void {
                /** @var User $user */
                $user = auth()->user();

                try {
                    BillingApprovals::requestRefund($record->patient, RefundSource::Invoice, (int) $data['amount'], PaymentMethod::from($data['method']), $data['reason'], $user, $record->branch_id, $record);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Refund belum diajukan')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Pengajuan refund dikirim ke owner')->success()->send();
            });
    }

    public static function printReceipt(): Action
    {
        return Action::make('printReceipt')
            ->label('Cetak struk')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => ! $record->isDraft())
            ->url(fn (Invoice $record): string => route('billing.invoice-receipt', $record), shouldOpenInNewTab: true);
    }

    public static function printNote(): Action
    {
        return Action::make('printNote')
            ->label('Nota PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Invoice $record): bool => ! $record->isDraft())
            ->url(fn (Invoice $record): string => route('billing.invoice-pdf', $record), shouldOpenInNewTab: true);
    }

    /** @return array<string, mixed> isi awal jendela "Terima pembayaran" */
    public static function paymentFormData(Invoice $record): array
    {
        return [
            'invoice_ids' => CheckoutPanel::otherOutstanding($record)->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
            'payments' => [['method' => PaymentMethod::Cash->value, 'amount' => self::defaultDue($record)]],
            'opening_cash' => CashSessions::suggestedOpeningCash($record->branch_id),
        ];
    }

    /** @return Collection<int, Invoice> tagihan ini + tagihan pasien lain yang belum lunas */
    private static function payableInvoices(Invoice $record): Collection
    {
        return collect([$record])->merge(CheckoutPanel::otherOutstanding($record))->sortBy(fn (Invoice $i) => $i->issued_at->timestamp ?? PHP_INT_MAX)->values();
    }

    private static function defaultDue(Invoice $record): int
    {
        return (int) self::payableInvoices($record)->sum(fn (Invoice $invoice): int => $invoice->balance());
    }

    private static function dueFor(Invoice $record, mixed $ids): int
    {
        $ids = collect(is_array($ids) ? $ids : [])->map(fn ($id): int => (int) $id)->push($record->id);

        return (int) self::payableInvoices($record)->whereIn('id', $ids->all())->sum(fn (Invoice $invoice): int => $invoice->balance());
    }

    /** @return array<string, string> */
    private static function methodOptions(Invoice $record): array
    {
        $options = PaymentMethod::moneyInOptions();
        $balance = $record->patient->depositBalance();

        if ($balance > 0) {
            $options[PaymentMethod::Deposit->value] = 'Deposit (saldo '.Money::format($balance).')';
        }

        return $options;
    }

    private static function cashTotal(mixed $payments): int
    {
        return (int) collect(is_array($payments) ? $payments : [])
            ->where('method', PaymentMethod::Cash->value)
            ->sum(fn (array $line): int => is_numeric($line['amount'] ?? null) ? (int) $line['amount'] : 0);
    }

    private static function changeHint(Get $get): ?string
    {
        $tendered = $get('cash_tendered');

        if (! is_numeric($tendered)) {
            return null;
        }

        $change = (int) $tendered - self::cashTotal($get('payments'));

        return $change >= 0 ? 'Kembalian '.Money::format($change) : 'Kurang '.Money::format(-$change);
    }

    private static function session(Invoice $record): ?CashSession
    {
        $user = auth()->user();

        return $user instanceof User ? CashSessions::current($user, $record->branch_id) : null;
    }
}
