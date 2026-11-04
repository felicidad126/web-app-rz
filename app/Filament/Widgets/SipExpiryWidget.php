<?php

namespace App\Filament\Widgets;

use App\Models\Practitioner;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Daftar nakes dengan SIP yang kedaluwarsa atau segera kedaluwarsa. */
class SipExpiryWidget extends TableWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $days = (int) config('clinic.sip_expiry_warning_days');

        return $table
            ->heading("SIP kedaluwarsa / ≤ {$days} hari lagi")
            ->query(fn (): Builder => Practitioner::query()->with('user')->sipExpiringWithin($days)->orderBy('sip_expired_at'))
            ->emptyStateHeading('Semua SIP masih berlaku')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated([5])
            ->columns([
                TextColumn::make('user.name')->label('Nama'),
                TextColumn::make('sip_number')->label('No. SIP')->placeholder('-'),
                TextColumn::make('sip_expired_at')
                    ->label('Berlaku sampai')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Practitioner $record): string => $record->isSipExpired() ? 'danger' : 'warning')
                    ->description(fn (Practitioner $record): string => $record->isSipExpired() ? 'Sudah kedaluwarsa' : $record->sip_expired_at->diffForHumans()),
            ]);
    }
}
