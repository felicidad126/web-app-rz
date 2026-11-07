<?php

namespace App\Filament\Clusters\Stock\Pages;

use App\Enums\PurchaseCashType;
use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Clusters\Stock\StockActions;
use App\Filament\Clusters\Stock\StockCluster;
use App\Models\PurchaseCashEntry;
use App\Services\Purchasing\Purchasing;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Tab "Kas pembelian": uang belanja BHP per cabang (setoran, pembayaran pesanan, pengeluaran lain).
 * Buku ini tidak bisa diubah; salah catat dikoreksi dengan entri pembalik.
 */
class PurchaseCash extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.stock.purchase-cash';

    protected static ?string $cluster = StockCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Kas pembelian';

    protected static ?string $title = 'Stok';

    protected static ?string $slug = 'kas-pembelian';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:PurchaseCash') ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        $branchId = BranchContext::id();

        if ($branchId === null) {
            return 'Anda belum ditugaskan ke cabang mana pun.';
        }

        return 'Kas pembelian · '.StockActions::branchName().' · saldo '.Money::format(Purchasing::balance($branchId));
    }

    public function table(Table $table): Table
    {
        $branchId = (int) BranchContext::id();

        return $table
            ->query(fn (): Builder => PurchaseCashEntry::query()
                ->with(['purchaseOrder', 'creator'])
                ->where('branch_id', $branchId)
                ->select('purchase_cash_entries.*')
                // Saldo berjalan per baris (urut id) dan penanda entri yang sudah dikoreksi.
                ->selectSub(DB::table('purchase_cash_entries as running')
                    ->selectRaw('coalesce(sum(running.amount), 0)')
                    ->whereColumn('running.branch_id', 'purchase_cash_entries.branch_id')
                    ->whereColumn('running.id', '<=', 'purchase_cash_entries.id'), 'running_balance')
                ->selectSub(DB::table('purchase_cash_entries as fix')
                    ->selectRaw('count(*)')
                    ->whereColumn('fix.reverses_entry_id', 'purchase_cash_entries.id'), 'is_reversed'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Belum ada transaksi kas pembelian')
            ->emptyStateDescription('Mulai dengan "Setor kas". Pembayaran pesanan dicatat dari halaman pesanan atau saat membuat pesanan.')
            ->columns([
                TextColumn::make('entry_date')->label('Tanggal')->date('d/m/Y')->visibleFrom('md')
                    ->description(fn (PurchaseCashEntry $record): ?string => $record->creator?->name),
                TextColumn::make('type')->label('Jenis')->badge()->color('gray')->visibleFrom('lg'),
                // Di HP tanggal ikut di bawah keterangan.
                TextColumn::make('description')->label('Keterangan')->wrap()->grow()
                    ->description(fn (PurchaseCashEntry $record): HtmlString => new HtmlString(
                        '<span class="klinik-only-sm">'.e($record->entry_date->format('d/m/Y')).($record->getAttribute('is_reversed') ? ' · ' : '').'</span>'
                        .($record->getAttribute('is_reversed') ? 'Sudah dikoreksi' : '')
                    ))
                    ->url(fn (PurchaseCashEntry $record): ?string => $record->purchaseOrder ? PurchaseOrderResource::getUrl('view', ['record' => $record->purchaseOrder]) : null),
                TextColumn::make('amount')->label('Jumlah')->alignEnd()->hiddenFrom('md')
                    ->formatStateUsing(fn (int $state): string => ($state < 0 ? '−' : '+').Money::format(abs($state), false)),
                TextColumn::make('in')->label('Masuk')->alignEnd()->visibleFrom('md')
                    ->state(fn (PurchaseCashEntry $record): ?string => $record->amount > 0 ? Money::format($record->amount, false) : null)
                    ->placeholder(''),
                TextColumn::make('out')->label('Keluar')->alignEnd()->visibleFrom('md')
                    ->state(fn (PurchaseCashEntry $record): ?string => $record->amount < 0 ? Money::format(-$record->amount, false) : null)
                    ->placeholder(''),
                TextColumn::make('running_balance')->label('Saldo')->alignEnd()->visibleFrom('sm')
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false)),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(PurchaseCashType::class)->multiple(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('entry_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('entry_date', '<=', $d))),
            ])
            ->recordActions([PurchaseActions::reverseCash()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            StockActions::branchSwitcher(),
            PurchaseActions::expense(),
            PurchaseActions::deposit(),
        ];
    }
}
