<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Enums\PaymentMethod;
use App\Enums\RefundSource;
use App\Models\CashSession;
use App\Models\DepositTransaction;
use App\Models\Patient;
use App\Models\User;
use App\Services\Billing\BillingApprovals;
use App\Services\Billing\CashSessions;
use App\Services\Billing\DepositLedger;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Deposit pasien: ledger saldo berjalan, top up, dan pengajuan refund saldo. */
class DepositRelationManager extends RelationManager
{
    protected static string $relationship = 'depositTransactions';

    protected static ?string $title = 'Deposit';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('View:Deposit') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var Patient $patient */
        $patient = $this->getOwnerRecord();

        return $table
            ->heading('Deposit · saldo '.Money::format($patient->depositBalance()))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Belum ada deposit')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                TextColumn::make('type')->label('Jenis')->badge()
                    ->description(fn (DepositTransaction $record): ?string => $record->method?->getLabel()),
                TextColumn::make('amount')->label('Mutasi')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').Money::format($state, false))
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('balance_after')->label('Saldo')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false)),
                TextColumn::make('notes')->label('Keterangan')->placeholder('—')->wrap(),
                TextColumn::make('creator.name')->label('Oleh')->visibleFrom('lg'),
            ])
            ->headerActions([
                Action::make('topUp')
                    ->label('Top up deposit')
                    ->icon(Heroicon::OutlinedPlus)
                    ->authorize(fn (): bool => auth()->user()?->can('TopUp:Deposit') ?? false)
                    ->schema([
                        Section::make('Sesi kasir belum dibuka')
                            ->description('Isi kas awal di laci untuk membuka sesi kasir.')
                            ->visible(fn (): bool => $this->session() === null)
                            ->schema([TextInput::make('opening_cash')->label('Kas awal')->prefix('Rp')->numeric()->minValue(0)->required()
                                ->default(fn (): int => CashSessions::suggestedOpeningCash((int) BranchContext::id()))]),
                        TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->minValue(1)->required(),
                        Select::make('method')->label('Metode')->options(PaymentMethod::moneyInOptions())->default(PaymentMethod::Cash->value)->required(),
                        TextInput::make('notes')->label('Keterangan')->placeholder('Mis. titipan untuk perawatan ortho')->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        /** @var Patient $patient */
                        $patient = $this->getOwnerRecord();

                        try {
                            $session = $this->session() ?? CashSessions::open($user, (int) BranchContext::id(), (int) ($data['opening_cash'] ?? 0));
                            DepositLedger::topUp($patient, $session, (int) $data['amount'], PaymentMethod::from($data['method']), $data['notes'] ?? null);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Top up belum disimpan')->body($e->getMessage())->danger()->send();

                            throw new Halt;
                        }

                        Notification::make()->title('Deposit bertambah '.Money::format((int) $data['amount']))->success()->send();
                    }),
                Action::make('refund')
                    ->label('Ajukan refund saldo')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('gray')
                    ->visible(fn (): bool => $this->ownerPatient()->depositBalance() > 0)
                    ->authorize(fn (): bool => auth()->user()?->can('TopUp:Deposit') ?? false)
                    ->modalDescription('Saldo deposit dikembalikan ke pasien setelah disetujui owner.')
                    ->schema([
                        TextInput::make('amount')->label('Nominal')->prefix('Rp')->numeric()->minValue(1)->required()
                            ->default(fn (): int => $this->ownerPatient()->depositBalance()),
                        Select::make('method')->label('Dikembalikan lewat')->options([PaymentMethod::Cash->value => 'Tunai', PaymentMethod::Transfer->value => 'Transfer'])->default(PaymentMethod::Cash->value)->required(),
                        Textarea::make('reason')->label('Alasan')->required()->minLength(5)->rows(2),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            BillingApprovals::requestRefund($this->ownerPatient(), RefundSource::Deposit, (int) $data['amount'], PaymentMethod::from($data['method']), $data['reason'], $user, (int) BranchContext::id());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Refund belum diajukan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Pengajuan refund deposit dikirim ke owner')->success()->send();
                    }),
            ]);
    }

    private function ownerPatient(): Patient
    {
        /** @var Patient $patient */
        $patient = $this->getOwnerRecord();

        return $patient;
    }

    private function session(): ?CashSession
    {
        $user = auth()->user();

        return $user instanceof User ? CashSessions::current($user, BranchContext::id()) : null;
    }
}
