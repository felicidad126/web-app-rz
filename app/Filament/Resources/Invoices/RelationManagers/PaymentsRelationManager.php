<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\BillingApprovals;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pembayaran';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('View:Invoice') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['transaction.receiver', 'voidRequester', 'voider']))
            ->defaultSort('id')
            ->paginated(false)
            ->emptyStateHeading('Belum ada pembayaran')
            ->columns([
                TextColumn::make('transaction.paid_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('method')->label('Metode')->badge()->color('gray'),
                TextColumn::make('reference_no')->label('Referensi')->placeholder('—')->visibleFrom('md'),
                TextColumn::make('amount')->label('Jumlah')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false)),
                TextColumn::make('transaction.receiver.name')->label('Kasir')->visibleFrom('md'),
                TextColumn::make('status')->label('Status')->badge()->extraAttributes(['class' => 'klinik-status'])
                    ->description(fn (Payment $record): ?string => $record->void_reason ? 'Alasan: '.$record->void_reason : null),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label('Struk')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Payment $record): string => route('billing.receipt', $record->payment_transaction_id), shouldOpenInNewTab: true),
                Action::make('requestVoid')
                    ->label('Ajukan void')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Active)
                    ->authorize(fn (): bool => auth()->user()?->can('Update:Invoice') ?? false)
                    ->modalDescription('Untuk salah input (metode atau nominal). Uang tidak berpindah; owner menyetujui, lalu catat ulang pembayaran yang benar.')
                    ->schema([Textarea::make('reason')->label('Alasan')->placeholder('Mis. salah pilih metode: Debit → QRIS')->required()->minLength(5)->rows(2)])
                    ->action(function (Payment $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            BillingApprovals::requestPaymentVoid($record, $data['reason'], $user);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Belum bisa diajukan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Pengajuan void dikirim ke owner')->success()->send();
                    }),
            ]);
    }
}
