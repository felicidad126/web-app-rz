<?php

namespace App\Filament\Clusters\Stock\Resources\StockOpnames\Pages;

use App\Enums\StockOpnameStatus;
use App\Filament\Clusters\Stock\Resources\StockMovements\StockMovementResource;
use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\Stock\StockOpnames;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var StockOpname $opname */
        $opname = $this->getRecord();
        $opname->loadMissing(['branch', 'creator', 'approver', 'lines.item.baseUnit']);

        return $schema->columns(1)->components([View::make('filament.stock.opname')->viewData(['opname' => $opname])]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var StockOpname $opname */
        $opname = $this->getRecord();

        return 'Stok opname '.$opname->number;
    }

    public function getSubheading(): ?string
    {
        /** @var StockOpname $opname */
        $opname = $this->getRecord();

        return $opname->status->getLabel().' · '.$opname->branch->name.' · dihitung '.$opname->creator?->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui & catat koreksi')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (StockOpname $record): bool => $record->status === StockOpnameStatus::Pending)
                ->authorize(fn (): bool => auth()->user()?->can('Approve:Stock') ?? false)
                ->requiresConfirmation()
                ->modalDescription('Selisih setiap baris dicatat sebagai mutasi koreksi, dan stok sistem menjadi sama dengan hasil hitung.')
                ->action(fn (StockOpname $record) => $this->decide(fn (User $u) => StockOpnames::approve($record, $u), 'Opname disetujui')),
            Action::make('reject')
                ->label('Kembalikan untuk dihitung ulang')
                ->color('gray')
                ->visible(fn (StockOpname $record): bool => $record->status === StockOpnameStatus::Pending)
                ->authorize(fn (): bool => auth()->user()?->can('Approve:Stock') ?? false)
                ->schema([Textarea::make('reason')->label('Yang perlu dicek ulang')->required()->minLength(5)])
                ->action(fn (StockOpname $record, array $data) => $this->decide(fn (User $u) => StockOpnames::reject($record, $u, $data['reason']), 'Dikembalikan ke penghitung')),
            Action::make('adjustment')
                ->label('Lihat mutasi koreksi')
                ->color('gray')
                ->visible(fn (StockOpname $record): bool => $record->adjustment_movement_id !== null)
                ->url(fn (StockOpname $record): string => StockMovementResource::getUrl('view', ['record' => $record->adjustment_movement_id])),
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
        $this->redirect(StockOpnameResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
