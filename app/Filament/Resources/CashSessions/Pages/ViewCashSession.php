<?php

namespace App\Filament\Resources\CashSessions\Pages;

use App\Filament\Resources\CashSessions\CashSessionResource;
use App\Models\CashSession;
use App\Services\Billing\CashSessions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewCashSession extends ViewRecord
{
    protected static string $resource = CashSessionResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var CashSession $session */
        $session = $this->getRecord();
        $session->loadMissing(['user', 'branch', 'closer']);

        return $schema->columns(1)->components([
            View::make('filament.billing.cash-session-summary')->viewData([
                'session' => $session,
                'system' => $session->isOpen() ? CashSessions::systemTotals($session) : null,
            ]),
            View::make('filament.billing.session-voids')->viewData(['session' => $session]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var CashSession $session */
        $session = $this->getRecord();

        return $session->label();
    }

    public function getSubheading(): ?string
    {
        /** @var CashSession $session */
        $session = $this->getRecord();
        $session->loadMissing(['branch', 'closer']);

        // Kasir & jam buka sudah ada di judul.
        return $session->branch->name.' · '.($session->closed_at
            ? 'ditutup '.$session->closed_at->format('d/m/Y H:i').($session->closer ? ' oleh '.$session->closer->name : '')
            : 'masih terbuka');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close')
                ->label('Tutup kasir')
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (CashSession $record): bool => $record->isOpen() && $record->user_id === auth()->id())
                ->url(fn (CashSession $record): string => CashSessionResource::getUrl('close', ['record' => $record])),
        ];
    }
}
