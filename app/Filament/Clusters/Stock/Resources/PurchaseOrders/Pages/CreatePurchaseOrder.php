<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Support\MoneyInput;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Buat pesanan ke supplier; baris bisa diambil dari permintaan yang sudah disetujui (?permintaan=1,2). */
class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string|Htmlable
    {
        return 'Buat pesanan · '.StockActions::branchName();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(['default' => 1, 'md' => 3])->schema([
                Select::make('supplier_id')->label('Supplier')
                    ->options(fn (): array => Supplier::where('is_active', true)->where('is_lab', false)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->label('Nama supplier')->required()->maxLength(150),
                        TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
                    ])
                    ->createOptionUsing(fn (array $data): int => Supplier::create($data)->id)
                    ->createOptionAction(fn (Action $action): Action => $action->authorize(fn (): bool => auth()->user()?->can('Create:Supplier') ?? false)),
                DatePicker::make('order_date')->label('Tanggal pesan')->native(false)->displayFormat('d/m/Y')->default(today())->maxDate(today())->required(),
                DatePicker::make('expected_at')->label('Perkiraan datang')->native(false)->displayFormat('d/m/Y')->minDate(today()),
            ]),
            Section::make('Barang')->schema([
                Repeater::make('lines')
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make('Barang')->width('28%'),
                        TableColumn::make('Satuan')->width('16%'),
                        TableColumn::make('Jumlah')->width('10%'),
                        TableColumn::make('Harga / satuan')->width('17%'),
                        TableColumn::make('Subtotal')->width('12%'),
                        TableColumn::make('Permintaan')->width('17%'),
                    ])
                    ->schema([
                        StockActions::itemSelect(),
                        StockActions::unitSelect(),
                        TextInput::make('qty')->label('Jumlah')->numeric()->minValue(0.001)->required()->live(onBlur: true),
                        MoneyInput::make('unit_price')->label('Harga / satuan')->required()->live(onBlur: true),
                        Text::make(fn (Get $get): string => Money::format((int) round((float) $get('qty') * self::money($get('unit_price'))), false))
                            ->extraAttributes(['style' => 'display: block; text-align: right; font-variant-numeric: tabular-nums']),
                        Hidden::make('purchase_request_id'),
                        Hidden::make('request_number')->dehydrated(false),
                        Text::make(fn (Get $get): string => (string) ($get('request_number') ?: '—'))->extraAttributes(['class' => 'klinik-mono']),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah barang')
                    ->deleteAction(fn (Action $action): Action => $action->color('gray'))
                    ->live(),
                Text::make(fn (Get $get): string => 'Total '.Money::format(self::total($get('lines'))))
                    ->weight('semibold')
                    ->extraAttributes(['style' => 'display: block; text-align: right']),
            ]),
            Section::make()->columns(['default' => 1, 'md' => 2])->schema([
                Textarea::make('notes')->label('Catatan untuk pesanan')->rows(2),
                Toggle::make('pay_now')->label('Sudah dibayar dari kas pembelian')
                    ->helperText(fn (): string => 'Saldo kas pembelian '.Money::format(Purchasing::balance((int) BranchContext::id())).'. Bisa juga dibayar nanti dari halaman pesanan.')
                    ->visible(fn (): bool => auth()->user()?->can('Manage:PurchaseCash') ?? false),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromRequests')
                ->label('Ambil dari permintaan')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (): bool => self::approvedRequests()->isNotEmpty())
                ->modalDescription('Permintaan yang sudah disetujui dan belum dipesan.')
                ->schema([
                    CheckboxList::make('ids')->hiddenLabel()->required()
                        ->options(fn (): array => self::approvedRequests()
                            ->mapWithKeys(fn (PurchaseRequest $r): array => [$r->id => $r->number.' · '.$r->item->name.' · '.StockLedger::format((float) $r->quantity).' '.$r->unit->name])
                            ->all()),
                ])
                ->action(fn (array $data) => $this->addRequests(array_map('intval', $data['ids']))),
        ];
    }

    protected function afterFill(): void
    {
        $ids = array_filter(array_map('intval', explode(',', (string) request()->query('permintaan', ''))));

        if ($ids !== []) {
            // Pesanan dibuat untuk cabang permintaannya (bisa berbeda dari cabang yang sedang dipilih).
            $branchIds = PurchaseRequest::whereKey($ids)->distinct()->pluck('branch_id');

            if ($branchIds->count() === 1) {
                BranchContext::set((int) $branchIds->first());
            }

            $this->data['lines'] = [];
            $this->addRequests($ids);
        }
    }

    /** @param  list<int>  $ids */
    public function addRequests(array $ids): void
    {
        $existing = collect($this->data['lines'] ?? [])
            // Baris kosong bawaan dibuang supaya daftar hanya berisi permintaan yang dipilih.
            ->filter(fn (array $line): bool => filled($line['item_id'] ?? null))
            ->all();
        $taken = collect($existing)->pluck('purchase_request_id')->filter()->map(fn ($id): int => (int) $id)->all();

        foreach (self::approvedRequests()->whereIn('id', $ids) as $request) {
            if (in_array($request->id, $taken, true)) {
                continue;
            }

            $existing[(string) str()->uuid()] = [
                'item_id' => $request->item_id,
                'unit_id' => $request->unit_id,
                'qty' => (float) $request->quantity,
                'unit_price' => null,
                'purchase_request_id' => $request->id,
                'request_number' => $request->number,
            ];
        }

        $this->data['lines'] = $existing;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return Purchasing::createOrder(
                StockActions::branchId(),
                filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null,
                $data['order_date'],
                array_values(array_map(fn (array $line): array => [
                    'item_id' => (int) $line['item_id'],
                    'unit_id' => (int) $line['unit_id'],
                    'qty' => (float) $line['qty'],
                    'unit_price' => self::money($line['unit_price'] ?? 0),
                    'purchase_request_id' => filled($line['purchase_request_id'] ?? null) ? (int) $line['purchase_request_id'] : null,
                ], $data['lines'] ?? [])),
                $user,
                $data['expected_at'] ?? null,
                $data['notes'] ?? null,
                (bool) ($data['pay_now'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum tersimpan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        /** @var PurchaseOrder $order */
        $order = $this->getRecord();

        return 'Pesanan '.$order->number.' dibuat · '.Money::format($order->total);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseOrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    /** @return Collection<int, PurchaseRequest> */
    private static function approvedRequests(): Collection
    {
        return PurchaseRequest::query()->with(['item', 'unit'])
            ->where('branch_id', BranchContext::id())
            ->where('status', PurchaseRequestStatus::Approved)
            ->orderBy('id')
            ->get();
    }

    /** Rupiah dari isian (MoneyInput menampilkan "85.000" sebelum disimpan). */
    private static function money(mixed $value): int
    {
        return is_int($value) ? $value : (int) preg_replace('/\D/', '', (string) $value);
    }

    private static function total(mixed $lines): int
    {
        return (int) collect(is_array($lines) ? $lines : [])
            ->sum(fn (array $line): float => round((float) ($line['qty'] ?? 0) * self::money($line['unit_price'] ?? 0)));
    }
}
