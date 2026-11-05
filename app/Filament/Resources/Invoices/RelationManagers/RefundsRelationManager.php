<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Enums\RefundStatus;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\User;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    protected static ?string $title = 'Refund';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (auth()->user()?->can('View:Invoice') ?? false) && $ownerRecord instanceof Invoice && $ownerRecord->refunds()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['requester', 'decider']))
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('requested_at')->label('Diajukan')->dateTime('d/m/Y H:i')
                    ->description(fn (Refund $record): ?string => $record->requester?->name),
                TextColumn::make('amount')->label('Jumlah')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false)),
                TextColumn::make('method')->label('Lewat')->badge()->color('gray'),
                TextColumn::make('reason')->label('Alasan')->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (Refund $record): ?string => $record->decider ? $record->decider->name.' '.$record->decided_at?->format('d/m H:i') : null),
            ])
            ->recordActions([
                Action::make('payOut')
                    ->label('Serahkan dana')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn (Refund $record): bool => $record->status === RefundStatus::Approved)
                    ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Refund $record): string => 'Serahkan '.Money::format($record->amount).' ('.$record->method->getLabel().') ke pasien. Tercatat di sesi kasir Anda.')
                    ->action(function (Refund $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $session = CashSessions::current($user, $record->branch_id);

                        try {
                            if ($session === null) {
                                throw new InvalidArgumentException('Buka sesi kasir di cabang ini dulu.');
                            }

                            BillingApprovals::payOutRefund($record, $session, $user);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Dana belum diserahkan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Refund diserahkan')->success()->send();
                    }),
            ]);
    }
}
