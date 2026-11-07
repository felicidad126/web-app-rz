<?php

namespace App\Filament\Clusters\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockReason;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\ExpiryDate;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockOpnames;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;
use Throwable;

/**
 * Aksi stok yang dipakai di halaman Barang dan Mutasi: penerimaan, pemakaian, transfer, pemusnahan, opname.
 */
final class StockActions
{
    public static function receive(): Action
    {
        return Action::make('receive')
            ->label('Catat penerimaan')
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->color('primary')
            ->authorize(fn (): bool => auth()->user()?->can('Create:StockMovement') ?? false)
            ->modalHeading(fn (): string => 'Catat penerimaan barang · '.self::branchName())
            ->modalDescription('ED yang hanya tertulis bulan/tahun (mis. 08/2027) disimpan sebagai akhir bulan itu.')
            ->modalWidth('7xl')
            ->schema([
                Select::make('supplier_id')->label('Supplier')
                    ->options(fn (): array => Supplier::where('is_active', true)->where('is_lab', false)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('supplier_invoice_no')->label('No. faktur supplier')->maxLength(60),
                DatePicker::make('movement_date')->label('Tanggal terima')->native(false)->displayFormat('d/m/Y')->default(today())->maxDate(today())->required(),
                Repeater::make('lines')
                    ->label('Barang')
                    ->table([
                        TableColumn::make('Barang')->width('26%'),
                        TableColumn::make('Satuan')->width('14%'),
                        TableColumn::make('Jumlah')->width('10%'),
                        TableColumn::make('Harga / satuan')->width('16%'),
                        TableColumn::make('No. batch')->width('16%'),
                        TableColumn::make('ED')->width('14%'),
                    ])
                    ->schema([
                        self::itemSelect(),
                        self::unitSelect(),
                        TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required(),
                        TextInput::make('unit_price')->label('Harga / satuan')->numeric()->minValue(0)->prefix('Rp')->required(),
                        TextInput::make('batch_number')->label('No. batch')->maxLength(60),
                        TextInput::make('expired_at')->label('ED')->placeholder('08/2027')
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                try {
                                    ExpiryDate::parse($value);
                                } catch (InvalidArgumentException $e) {
                                    $fail($e->getMessage());
                                }
                            }),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah barang')
                    ->columnSpanFull(),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                self::run(fn () => StockLedger::receive(self::branchId(), array_values($data['lines']), $data['supplier_id'] ?? null, $data['supplier_invoice_no'] ?? null, $data['movement_date'], $data['notes'] ?? null, self::user()),
                    fn ($movement) => 'Penerimaan '.$movement->number.' tercatat · '.Money::format($movement->total_cost));
            });
    }

    public static function usage(): Action
    {
        return Action::make('usage')
            ->label('Catat pemakaian')
            ->modalHeading(fn (): string => 'Catat pemakaian · '.self::branchName())
            ->icon(Heroicon::OutlinedMinusCircle)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('Create:StockMovement') ?? false)
            ->modalDescription('Untuk bahan di luar daftar bahan tindakan (bahan tindakan terpotong otomatis saat rekam medis ditandatangani). Diambil dari batch dengan ED terdekat.')
            ->modalWidth('4xl')
            ->schema([
                Repeater::make('lines')->label('Barang')
                    ->table([TableColumn::make('Barang')->width('55%'), TableColumn::make('Satuan')->width('25%'), TableColumn::make('Jumlah')->width('20%')])
                    ->schema([self::itemSelect(), self::unitSelect(), TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required()])
                    ->minItems(1)->defaultItems(1)->addActionLabel('Tambah barang'),
                TextInput::make('notes')->label('Keperluan')->placeholder('Mis. sterilisasi, bahan tambahan pasien RM-000231')->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                self::run(fn () => StockLedger::issue(self::branchId(), StockMovementType::Usage, array_values($data['lines']), null, $data['notes'], self::user()),
                    fn ($movement) => 'Pemakaian '.$movement->number.' tercatat');
            });
    }

    public static function transfer(): Action
    {
        return Action::make('transfer')
            ->label('Transfer ke cabang lain')
            ->modalHeading(fn (): string => 'Transfer dari '.self::branchName())
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->authorize(fn (): bool => (auth()->user()?->can('Create:StockMovement') ?? false) && BranchContext::available()->count() > 1)
            ->modalWidth('4xl')
            ->schema([
                Select::make('to_branch_id')->label('Ke cabang')
                    ->options(fn (): array => BranchContext::available()->where('id', '!=', self::branchId())->pluck('name', 'id')->all())
                    ->required(),
                Repeater::make('lines')->label('Barang')
                    ->table([TableColumn::make('Barang')->width('55%'), TableColumn::make('Satuan')->width('25%'), TableColumn::make('Jumlah')->width('20%')])
                    ->schema([self::itemSelect(), self::unitSelect(), TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required()])
                    ->minItems(1)->defaultItems(1)->addActionLabel('Tambah barang'),
                TextInput::make('notes')->label('Catatan')->maxLength(255),
            ])
            ->action(function (array $data): void {
                self::run(fn () => StockLedger::transfer(self::branchId(), (int) $data['to_branch_id'], array_values($data['lines']), $data['notes'] ?? null, self::user()),
                    fn (array $pair) => 'Transfer '.$pair[0]->number.' tercatat di kedua cabang');
            });
    }

    public static function disposal(): Action
    {
        return Action::make('disposal')
            ->label('Ajukan pemusnahan')
            ->modalHeading(fn (): string => 'Ajukan pemusnahan · '.self::branchName())
            ->icon(Heroicon::OutlinedTrash)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('Create:StockMovement') ?? false)
            ->modalDescription('Untuk bahan kedaluwarsa, rusak, atau hilang. Stok berkurang setelah disetujui admin klinik, lalu berita acara bisa dicetak.')
            ->modalWidth('4xl')
            ->fillForm(fn (): array => ['reason' => StockReason::Expired->value, 'lines' => ItemBatch::query()
                ->where('branch_id', self::branchId())->where('is_unbatched', false)->where('qty_on_hand', '>', 0)
                ->whereDate('expired_at', '<', today())->orderBy('expired_at')->get()
                ->map(fn (ItemBatch $b): array => ['item_batch_id' => $b->id, 'qty' => (float) $b->qty_on_hand])->all()])
            ->schema([
                Select::make('reason')->label('Alasan')->options(StockReason::class)->required(),
                Repeater::make('lines')->label('Batch')
                    ->table([TableColumn::make('Batch')->width('75%'), TableColumn::make('Jumlah (satuan dasar)')->width('25%')])
                    ->schema([
                        Select::make('item_batch_id')->label('Batch')->options(fn (): array => self::batchOptions())->searchable()->required()->distinct(),
                        TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required(),
                    ])
                    ->minItems(1)->addActionLabel('Tambah batch'),
                Textarea::make('notes')->label('Keterangan')->placeholder('Mis. cara pemusnahan dan saksi')->rows(2),
            ])
            ->action(function (array $data): void {
                self::run(fn () => StockLedger::requestDisposal(self::branchId(), array_values($data['lines']), $data['reason'] instanceof StockReason ? $data['reason'] : StockReason::from($data['reason']), $data['notes'] ?? null, self::user()),
                    fn ($movement) => 'Pemusnahan '.$movement->number.' menunggu persetujuan');
            });
    }

    public static function startOpname(): Action
    {
        return Action::make('startOpname')
            ->label('Mulai opname')
            ->modalHeading(fn (): string => 'Mulai opname · '.self::branchName())
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->can('Create:StockOpname') ?? false)
            ->requiresConfirmation()
            ->modalDescription('Saldo sistem setiap batch dicatat sekarang. Hitung fisik semua barang, lalu ajukan untuk disetujui admin klinik.')
            ->action(function (Action $action): void {
                try {
                    $opname = StockOpnames::start(self::branchId(), self::user());
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Opname belum bisa dimulai')->body($e->getMessage())->danger()->send();

                    return;
                }

                $action->redirect(StockOpnameResource::getUrl('edit', ['record' => $opname]));
            });
    }

    public static function itemSelect(): Select
    {
        return Select::make('item_id')
            ->label('Barang')
            ->options(fn (): array => Item::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(fn ($state, $set) => $set('unit_id', Item::find($state)?->base_unit_id));
    }

    public static function unitSelect(): Select
    {
        return Select::make('unit_id')
            ->label('Satuan')
            ->options(fn (Get $get): array => self::unitOptions($get('item_id')))
            ->required();
    }

    /** @return array<int, string> satuan dasar + satuan konversi barang */
    public static function unitOptions(mixed $itemId): array
    {
        $item = $itemId ? Item::with(['baseUnit', 'units.unit'])->find($itemId) : null;

        if ($item === null) {
            return [];
        }

        $options = [$item->base_unit_id => $item->baseUnit->name];

        foreach ($item->units as $conversion) {
            $options[$conversion->unit_id] = $conversion->unit->name.' (= '.StockLedger::format((float) $conversion->factor_to_base).' '.$item->baseUnit->name.')';
        }

        return $options;
    }

    /** @return array<int, string> batch berstok di cabang aktif */
    public static function batchOptions(): array
    {
        return ItemBatch::query()->with('item.baseUnit')
            ->where('branch_id', self::branchId())->where('is_unbatched', false)->where('qty_on_hand', '>', 0)
            ->orderBy('expired_at')->get()
            ->mapWithKeys(fn (ItemBatch $b): array => [$b->id => $b->item->name.' · '.$b->label().' · stok '.StockLedger::format((float) $b->qty_on_hand).' '.$b->item->baseUnit->name])
            ->all();
    }

    /** Pemilih cabang kerja (owner/admin dengan lebih dari satu cabang). */
    public static function branchSwitcher(): ActionGroup
    {
        return ActionGroup::make(BranchContext::available()
            ->map(fn (Branch $branch): Action => Action::make('branch'.$branch->id)
                ->label($branch->name)
                ->icon($branch->id === BranchContext::id() ? Heroicon::OutlinedCheck : null)
                ->action(function (Page $livewire) use ($branch): void {
                    BranchContext::set($branch->id);
                    $livewire->redirect($livewire::getUrl());
                }))
            ->all())
            ->label(fn (): string => self::branchName())
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->button()
            ->color('gray')
            ->visible(fn (): bool => BranchContext::available()->count() > 1);
    }

    public static function branchName(): string
    {
        return (string) BranchContext::available()->firstWhere('id', BranchContext::id())?->name;
    }

    public static function branchId(): int
    {
        $id = BranchContext::id();

        if ($id === null) {
            Notification::make()->title('Anda belum ditugaskan ke cabang mana pun')->danger()->send();

            throw new Halt;
        }

        return $id;
    }

    private static function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private static function run(callable $work, callable $message): void
    {
        try {
            $result = $work();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum tersimpan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Belum tersimpan')->body('Terjadi kesalahan. Coba lagi atau hubungi admin.')->danger()->send();

            throw new Halt;
        }

        Notification::make()->title($message($result))->success()
            ->actions([Action::make('open')->label('Lihat mutasi')->url(StockMovementResource::getUrl('index'))])
            ->send();
    }
}
