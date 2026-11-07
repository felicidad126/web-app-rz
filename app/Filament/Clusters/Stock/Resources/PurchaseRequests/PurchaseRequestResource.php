<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseRequests;

use App\Enums\PurchaseRequestStatus;
use App\Filament\Clinic\CodeText;
use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Clusters\Stock\StockCluster;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Models\PurchaseRequest;
use App\Services\Stock\StockLedger;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Tab "Permintaan": perawat meminta barang, admin/owner menyetujui, lalu dipesan ke supplier. */
class PurchaseRequestResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = PurchaseRequest::class;

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Permintaan';

    protected static ?string $modelLabel = 'permintaan pembelian';

    protected static ?string $pluralModelLabel = 'permintaan pembelian';

    protected static ?string $slug = 'permintaan';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    /** Dibuat lewat aksi "Ajukan permintaan"; tidak diubah setelah dikirim (batalkan lalu ajukan lagi). */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Badge hanya untuk yang bisa menyetujui: jumlah permintaan menunggu di cabang aktif. */
    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->can('Approve:Purchase') ?? false)) {
            return null;
        }

        $count = static::getEloquentQuery()->where('branch_id', BranchContext::id())->where('status', PurchaseRequestStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'item', 'unit', 'requester', 'decider', 'purchaseOrder']))
            ->emptyStateHeading('Belum ada permintaan')
            ->emptyStateDescription('Permintaan muncul di sini saat perawat menekan "Minta beli" di tab Barang atau "Ajukan permintaan".')
            ->columns([
                // Di HP cukup Barang · Jumlah · Status: nomor, tanggal, dan pengaju ada di bawah nama barang.
                TextColumn::make('item.name')->label('Barang')->searchable(['number'])->wrap()->grow()
                    ->description(fn (PurchaseRequest $record): HtmlString => CodeText::join([
                        [$record->number, true], $record->created_at->format('d/m H:i'), $record->requester?->name,
                    ])),
                TextColumn::make('reason')->label('Alasan')->wrap()->placeholder('—')->visibleFrom('lg'),
                TextColumn::make('quantity')->label('Jumlah')->alignEnd()
                    ->formatStateUsing(fn (PurchaseRequest $record): string => StockLedger::format((float) $record->quantity).' '.$record->unit->name),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (PurchaseRequest $record): ?string => match (true) {
                        $record->decider === null => null,
                        $record->status === PurchaseRequestStatus::Rejected => $record->decider->name.': '.$record->decision_note,
                        default => $record->decider->name.' · '.$record->decided_at?->format('d/m'),
                    }),
                TextColumn::make('purchaseOrder.number')->label('Pesanan')->fontFamily('mono')->placeholder('—')->visibleFrom('md')
                    ->url(fn (PurchaseRequest $record): ?string => $record->purchaseOrder && PurchaseOrderResource::canView($record->purchaseOrder)
                        ? PurchaseOrderResource::getUrl('view', ['record' => $record->purchaseOrder]) : null),
                TextColumn::make('branch.name')->label('Cabang')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id()),
            ])
            ->recordActions([
                PurchaseActions::approve(),
                ActionGroup::make([PurchaseActions::reject(), PurchaseActions::cancelRequest()]),
            ])
            ->toolbarActions([
                BulkAction::make('order')
                    ->label('Buat pesanan')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('gray')
                    ->authorize(fn (): bool => PurchaseOrderResource::canCreate())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, ListPurchaseRequests $livewire): void {
                        $approved = $records->where('status', PurchaseRequestStatus::Approved);

                        if ($approved->isEmpty()) {
                            Notification::make()->title('Pilih permintaan yang sudah disetujui')->warning()->send();

                            return;
                        }

                        if ($approved->pluck('branch_id')->unique()->count() > 1) {
                            Notification::make()->title('Pilih permintaan dari satu cabang')->body('Satu pesanan untuk satu cabang.')->warning()->send();

                            return;
                        }

                        $ids = $approved->pluck('id');

                        $livewire->redirect(PurchaseOrderResource::getUrl('create', ['permintaan' => $ids->implode(',')]));
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequestStatus::Approved);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
        ];
    }
}
