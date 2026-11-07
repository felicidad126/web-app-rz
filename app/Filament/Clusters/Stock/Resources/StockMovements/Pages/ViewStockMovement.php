<?php

namespace App\Filament\Clusters\Stock\Resources\StockMovements\Pages;

use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Purchasing\Purchasing;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var StockMovement $movement */
        $movement = $this->getRecord();
        $movement->loadMissing(['branch', 'supplier', 'counterpartBranch', 'creator', 'approver', 'reverses', 'source', 'lines.item.baseUnit', 'lines.batch', 'lines.enteredUnit']);

        return $schema->columns(1)->components([View::make('filament.stock.movement')->viewData(['movement' => $movement])]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var StockMovement $movement */
        $movement = $this->getRecord();

        return $movement->type->getLabel().' '.$movement->number;
    }

    public function getSubheading(): ?string
    {
        /** @var StockMovement $movement */
        $movement = $this->getRecord();

        return $movement->status->getLabel().' · '.$movement->movement_date->format('d/m/Y').' · '.$movement->branch->name.' · nilai '.Money::format($movement->total_cost);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (StockMovement $record): bool => $record->status === StockMovementStatus::Pending)
                ->authorize(fn (): bool => auth()->user()?->can('Approve:Stock') ?? false)
                ->requiresConfirmation()
                ->modalDescription('Stok batch berkurang sesuai daftar dan tidak bisa diubah lagi.')
                ->action(fn (StockMovement $record) => $this->decide(fn (User $user) => StockLedger::approve($record, $user), 'Disetujui dan tercatat')),
            Action::make('reject')
                ->label('Tolak')
                ->color('danger')
                ->visible(fn (StockMovement $record): bool => $record->status === StockMovementStatus::Pending)
                ->authorize(fn (): bool => auth()->user()?->can('Approve:Stock') ?? false)
                ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(5)])
                ->action(fn (StockMovement $record, array $data) => $this->decide(fn (User $user) => StockLedger::reject($record, $user, $data['reason']), 'Ditolak')),
            Action::make('cancel')
                ->label('Batalkan mutasi')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (StockMovement $record): bool => $record->isPosted()
                    && in_array($record->type, [StockMovementType::Receipt, StockMovementType::Usage, StockMovementType::Dispensing], true)
                    && StockMovement::query()->where('reverses_movement_id', $record->id)->doesntExist())
                ->authorize(fn (): bool => auth()->user()?->can('Approve:Stock') ?? false)
                ->modalDescription('Untuk salah input atau barang yang dikembalikan. Setiap baris dikembalikan ke batch asalnya lewat mutasi pembatalan.')
                ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(5)])
                ->action(fn (StockMovement $record, array $data) => $this->decide(
                    // Penerimaan dari pesanan: jumlah diterima di pesanan ikut dikembalikan.
                    fn (User $user) => $record->source_type === (new PurchaseOrder)->getMorphClass()
                        ? Purchasing::cancelReceipt($record, $data['reason'], $user)
                        : StockLedger::cancel($record, $data['reason'], $user),
                    'Mutasi dibatalkan',
                )),
            Action::make('pdf')
                ->label('Cetak berita acara')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (StockMovement $record): string => route('stock.movement-pdf', $record), shouldOpenInNewTab: true),
        ];
    }

    private function decide(callable $work, string $message): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $work($user);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum bisa diproses')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($message)->success()->send();
        $this->redirect(StockMovementResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
