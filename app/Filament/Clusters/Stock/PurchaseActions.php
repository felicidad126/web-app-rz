<?php

namespace App\Filament\Clusters\Stock;

use App\Enums\PurchaseCashType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Support\MoneyInput;
use App\Models\Item;
use App\Models\PurchaseCashEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\ExpiryDate;
use App\Services\Stock\StockAlerts;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Throwable;

/**
 * Aksi pembelian (permintaan, pesanan, penerimaan, kas pembelian) untuk tab Stok.
 * Semua aturan ada di App\Services\Purchasing\Purchasing; di sini hanya form & notifikasi.
 */
final class PurchaseActions
{
    // ---------------------------------------------------------------------
    // Permintaan
    // ---------------------------------------------------------------------

    /** "Ajukan permintaan" (header) atau "Minta beli" (baris barang, barang sudah terisi). */
    public static function requestItem(bool $forRow = false): Action
    {
        return Action::make($forRow ? 'requestPurchase' : 'createRequest')
            ->label($forRow ? 'Minta beli' : 'Ajukan permintaan')
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color($forRow ? 'gray' : 'primary')
            ->authorize(fn (): bool => auth()->user()?->can('Create:PurchaseRequest') ?? false)
            ->modalHeading(fn (): string => 'Minta pembelian · '.StockActions::branchName())
            ->modalDescription('Permintaan disetujui admin/owner, lalu dipesan ke supplier. Stok bertambah saat barangnya datang.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Kirim permintaan')
            ->fillForm(fn (?Item $record = null): array => $record instanceof Item ? self::suggestion($record) : [])
            ->schema([
                StockActions::itemSelect()->label('Barang'),
                StockActions::unitSelect()->label('Satuan'),
                TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required(),
                Textarea::make('reason')->label('Alasan')->placeholder('Mis. stok tinggal 1 box, dipakai untuk scaling')->rows(2),
            ])
            ->action(function (array $data): void {
                self::run(
                    fn () => Purchasing::requestItem(StockActions::branchId(), (int) $data['item_id'], (float) $data['qty'], (int) $data['unit_id'], $data['reason'] ?? null, self::user()),
                    fn (PurchaseRequest $request): string => 'Permintaan '.$request->number.' terkirim',
                );
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->visible(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequestStatus::Pending && self::canDecide($record))
            ->authorize(fn (): bool => auth()->user()?->can('Approve:Purchase') ?? false)
            ->modalHeading(fn (PurchaseRequest $record): string => 'Setujui permintaan '.$record->number)
            ->modalDescription(fn (PurchaseRequest $record): string => $record->item->name.' · '.StockLedger::format((float) $record->quantity).' '.$record->unit->name)
            ->modalSubmitActionLabel('Setujui')
            ->schema([TextInput::make('note')->label('Catatan (opsional)')->maxLength(255)])
            ->action(fn (PurchaseRequest $record, array $data) => self::run(
                fn () => Purchasing::approve($record, self::user(), $data['note'] ?? null),
                fn (): string => 'Permintaan '.$record->number.' disetujui',
            ));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (PurchaseRequest $record): bool => in_array($record->status, [PurchaseRequestStatus::Pending, PurchaseRequestStatus::Approved], true) && self::canDecide($record))
            ->authorize(fn (): bool => auth()->user()?->can('Approve:Purchase') ?? false)
            ->modalHeading(fn (PurchaseRequest $record): string => 'Tolak permintaan '.$record->number)
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(2)])
            ->action(fn (PurchaseRequest $record, array $data) => self::run(
                fn () => Purchasing::reject($record, self::user(), $data['reason']),
                fn (): string => 'Permintaan '.$record->number.' ditolak',
            ));
    }

    public static function cancelRequest(): Action
    {
        return Action::make('cancelRequest')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (PurchaseRequest $record): bool => in_array($record->status, [PurchaseRequestStatus::Pending, PurchaseRequestStatus::Approved], true)
                && ($record->requested_by === auth()->id() || (auth()->user()?->can('Approve:Purchase') ?? false)))
            ->requiresConfirmation()
            ->modalHeading(fn (PurchaseRequest $record): string => 'Batalkan permintaan '.$record->number.'?')
            ->modalDescription('Permintaan tetap tercatat dengan status Dibatalkan.')
            ->action(fn (PurchaseRequest $record) => self::run(
                fn () => Purchasing::cancelRequest($record, self::user()),
                fn (): string => 'Permintaan '.$record->number.' dibatalkan',
            ));
    }

    // ---------------------------------------------------------------------
    // Pesanan
    // ---------------------------------------------------------------------

    public static function receive(): Action
    {
        return Action::make('receiveOrder')
            ->label('Terima barang')
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->color('primary')
            ->visible(fn (PurchaseOrder $record): bool => $record->status->isOpen())
            ->authorize(fn (): bool => auth()->user()?->can('Receive:Purchase') ?? false)
            ->modalHeading(fn (PurchaseOrder $record): string => 'Terima barang · '.$record->number)
            ->modalDescription('Isi jumlah yang benar-benar datang (boleh sebagian). ED yang hanya tertulis bulan/tahun (mis. 08/2027) disimpan sebagai akhir bulan itu.')
            ->modalWidth('6xl')
            ->modalSubmitActionLabel('Catat penerimaan')
            ->fillForm(fn (PurchaseOrder $record): array => [
                'date' => today(),
                'lines' => $record->lines()->with(['item', 'unit'])->get()
                    ->filter(fn (PurchaseOrderLine $line): bool => $line->remainingQuantity() > 0)
                    ->map(fn (PurchaseOrderLine $line): array => [
                        'line_id' => $line->id,
                        'label' => $line->item->name.' · sisa '.StockLedger::format($line->remainingQuantity()).' '.$line->unit->name,
                        'qty' => $line->remainingQuantity(),
                    ])->values()->all(),
            ])
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    DatePicker::make('date')->label('Tanggal terima')->native(false)->displayFormat('d/m/Y')->maxDate(today())->required(),
                    TextInput::make('invoice_no')->label('No. faktur supplier')->maxLength(60),
                ]),
                Repeater::make('lines')
                    ->label('Barang')
                    ->table([
                        TableColumn::make('Barang · sisa')->width('38%'),
                        TableColumn::make('Diterima')->width('14%'),
                        TableColumn::make('No. batch')->width('24%'),
                        TableColumn::make('ED')->width('24%'),
                    ])
                    ->schema([
                        Hidden::make('line_id'),
                        Hidden::make('label')->dehydrated(false),
                        Text::make(fn (Get $get): string => (string) $get('label')),
                        TextInput::make('qty')->label('Diterima')->numeric()->minValue(0),
                        TextInput::make('batch_number')->label('No. batch')->maxLength(60),
                        TextInput::make('expired_at')->label('ED')->placeholder('08/2027')->rule(fn (): \Closure => self::expiryRule()),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull(),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ])
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => Purchasing::receive($record, array_values($data['lines']), self::user(), $data['date'], $data['invoice_no'] ?? null, $data['notes'] ?? null),
                fn ($movement): string => 'Penerimaan '.$movement->number.' tercatat · stok bertambah',
            ));
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('Catat pembayaran')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status !== PurchaseOrderStatus::Cancelled && $record->unpaidAmount() > 0)
            ->authorize(fn (): bool => auth()->user()?->can('Manage:PurchaseCash') ?? false)
            ->modalHeading(fn (PurchaseOrder $record): string => 'Bayar pesanan '.$record->number)
            ->modalDescription(fn (PurchaseOrder $record): string => 'Dibayar dari kas pembelian. Saldo kas sekarang '.Money::format(Purchasing::balance($record->branch_id)).'.')
            ->fillForm(fn (PurchaseOrder $record): array => ['amount' => $record->unpaidAmount(), 'date' => today()])
            ->schema([
                MoneyInput::make('amount')->label('Nominal')->required(),
                DatePicker::make('date')->label('Tanggal bayar')->native(false)->displayFormat('d/m/Y')->maxDate(today())->required(),
            ])
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => Purchasing::pay($record, (int) $data['amount'], self::user(), $data['date']),
                fn (): string => 'Pembayaran '.Money::format((int) $data['amount']).' tercatat',
            ));
    }

    public static function closeRemaining(): Action
    {
        return Action::make('closeRemaining')
            ->label('Tutup sisa pesanan')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Partial)
            ->authorize(fn (): bool => auth()->user()?->can('Update:PurchaseOrder') ?? false)
            ->modalDescription('Untuk sisa yang tidak akan dikirim supplier. Barang yang sudah diterima tetap tercatat.')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(2)])
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => Purchasing::closeRemaining($record, $data['reason'], self::user()),
                fn (): string => 'Pesanan '.$record->number.' ditutup',
            ));
    }

    public static function cancelOrder(): Action
    {
        return Action::make('cancelOrder')
            ->label('Batalkan pesanan')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Ordered)
            ->authorize(fn (): bool => auth()->user()?->can('Update:PurchaseOrder') ?? false)
            ->modalHeading(fn (PurchaseOrder $record): string => 'Batalkan pesanan '.$record->number.'?')
            ->modalDescription(fn (PurchaseOrder $record): string => $record->paid_amount > 0
                ? 'Pembayaran '.Money::format($record->paid_amount).' dikembalikan ke kas pembelian. Permintaan di pesanan ini bisa dipesan ulang.'
                : 'Permintaan di pesanan ini bisa dipesan ulang.')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(2)])
            ->action(fn (PurchaseOrder $record, array $data) => self::run(
                fn () => Purchasing::cancelOrder($record, $data['reason'], self::user()),
                fn (): string => 'Pesanan '.$record->number.' dibatalkan',
            ));
    }

    // ---------------------------------------------------------------------
    // Kas pembelian
    // ---------------------------------------------------------------------

    public static function deposit(): Action
    {
        return Action::make('deposit')
            ->label('Setor kas')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->authorize(fn (): bool => auth()->user()?->can('Manage:PurchaseCash') ?? false)
            ->modalHeading(fn (): string => 'Setor kas pembelian · '.StockActions::branchName())
            ->fillForm(['date' => today()])
            ->schema(self::cashSchema('Mis. setoran dari owner minggu ke-2'))
            ->action(fn (array $data) => self::run(
                fn () => Purchasing::deposit(StockActions::branchId(), (int) $data['amount'], $data['description'], self::user(), $data['date']),
                fn (): string => 'Setoran '.Money::format((int) $data['amount']).' tercatat',
            ));
    }

    public static function expense(): Action
    {
        return Action::make('expense')
            ->label('Catat pengeluaran')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('Manage:PurchaseCash') ?? false)
            ->modalHeading(fn (): string => 'Pengeluaran kas pembelian · '.StockActions::branchName())
            ->modalDescription('Untuk biaya tanpa pesanan, mis. ongkos kirim atau beli langsung di toko. Pembayaran pesanan dicatat dari halaman pesanannya.')
            ->fillForm(['date' => today()])
            ->schema(self::cashSchema('Mis. ongkos kirim PT Dental Sehat'))
            ->action(fn (array $data) => self::run(
                fn () => Purchasing::expense(StockActions::branchId(), (int) $data['amount'], $data['description'], self::user(), $data['date']),
                fn (): string => 'Pengeluaran '.Money::format((int) $data['amount']).' tercatat',
            ));
    }

    public static function reverseCash(): Action
    {
        return Action::make('reverseCash')
            ->label('Koreksi')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (PurchaseCashEntry $record): bool => in_array($record->type, [PurchaseCashType::Deposit, PurchaseCashType::Expense], true)
                && ! $record->getAttribute('is_reversed'))
            ->authorize(fn (): bool => auth()->user()?->can('Manage:PurchaseCash') ?? false)
            ->modalHeading('Koreksi entri kas')
            ->modalDescription(fn (PurchaseCashEntry $record): string => 'Entri "'.$record->description.'" ('.Money::format($record->amount).') dibalik dengan entri koreksi. Entri asli tetap tercatat.')
            ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(2)])
            ->action(fn (PurchaseCashEntry $record, array $data) => self::run(
                fn () => Purchasing::reverseCash($record, $data['reason'], self::user()),
                fn (): string => 'Koreksi tercatat',
            ));
    }

    // ---------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------

    /** @return array{item_id: int, unit_id: int, qty: float, reason: string|null} saran: isi sampai 2× minimum */
    private static function suggestion(Item $item): array
    {
        $stock = StockAlerts::stockByItem(StockActions::branchId())[$item->id] ?? 0.0;
        $minimum = (float) $item->min_stock;

        return [
            'item_id' => $item->id,
            'unit_id' => $item->base_unit_id,
            'qty' => max(1.0, ceil($minimum * 2 - $stock)),
            'reason' => $minimum > 0 && $stock < $minimum ? 'Stok '.StockLedger::format($stock).', di bawah minimum '.StockLedger::format($minimum) : null,
        ];
    }

    private static function canDecide(PurchaseRequest $request): bool
    {
        return $request->requested_by !== auth()->id();
    }

    /** @return list<Component|Field> */
    private static function cashSchema(string $placeholder): array
    {
        return [
            MoneyInput::make('amount')->label('Nominal')->required(),
            DatePicker::make('date')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->maxDate(today())->required(),
            TextInput::make('description')->label('Keterangan')->placeholder($placeholder)->required()->maxLength(255),
        ];
    }

    private static function expiryRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            try {
                ExpiryDate::parse($value);
            } catch (InvalidArgumentException $e) {
                $fail($e->getMessage());
            }
        };
    }

    private static function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /** Jalankan aksi; kesalahan input tampil sebagai notifikasi dan modal tetap terbuka. */
    public static function run(callable $work, callable $message): mixed
    {
        try {
            $result = $work();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum tersimpan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        } catch (Halt $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Belum tersimpan')->body('Terjadi kesalahan. Coba lagi atau hubungi admin.')->danger()->send();

            throw new Halt;
        }

        Notification::make()->title($message($result))->success()->send();

        return $result;
    }

    /** Tautan ke daftar permintaan yang menunggu (untuk notifikasi & widget). */
    public static function pendingUrl(): string
    {
        return PurchaseRequestResource::getUrl('index', ['tab' => 'menunggu']);
    }
}
