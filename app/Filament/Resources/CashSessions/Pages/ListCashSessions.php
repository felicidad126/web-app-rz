<?php

namespace App\Filament\Resources\CashSessions\Pages;

use App\Filament\Resources\CashSessions\CashSessionResource;
use App\Models\User;
use App\Services\Billing\CashSessions;
use App\Support\BranchContext;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCashSessions extends ListRecords
{
    protected static string $resource = CashSessionResource::class;

    protected ?string $subheading = 'Buka sesi di awal shift; tutup di akhir shift dengan hitung fisik laci dan cocokkan tiap metode bayar.';

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $open = $user instanceof User ? CashSessions::current($user, BranchContext::id()) : null;

        return [
            Action::make('closeCurrent')
                ->label('Tutup sesi saya')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible($open !== null)
                ->url(fn (): ?string => $open ? CashSessionResource::getUrl('close', ['record' => $open]) : null),
            Action::make('open')
                ->label('Buka sesi kasir')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible($open === null && BranchContext::id() !== null)
                ->authorize(fn (): bool => auth()->user()?->can('Create:CashSession') ?? false)
                ->fillForm(fn (): array => ['opening_cash' => CashSessions::suggestedOpeningCash((int) BranchContext::id())])
                ->schema([TextInput::make('opening_cash')->label('Kas awal di laci')->prefix('Rp')->numeric()->minValue(0)->required()])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    CashSessions::open($user, (int) BranchContext::id(), (int) $data['opening_cash']);
                    Notification::make()->title('Sesi kasir dibuka')->success()->send();
                }),
        ];
    }
}
