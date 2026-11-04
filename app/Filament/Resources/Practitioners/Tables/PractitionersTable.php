<?php

namespace App\Filament\Resources\Practitioners\Tables;

use App\Enums\PractitionerType;
use App\Models\Practitioner;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PractitionersTable
{
    public static function configure(Table $table): Table
    {
        $warningDays = (int) config('clinic.sip_expiry_warning_days');

        return $table
            ->columns([
                TextColumn::make('user.name')->label('Nama')->description(fn (Practitioner $record): ?string => $record->specialty)->searchable()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('sip_number')->label('No. SIP')->placeholder('-')->visibleFrom('md'),
                TextColumn::make('sip_expired_at')
                    ->label('SIP berlaku')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-')
                    ->badge()
                    ->color(fn (Practitioner $record): string => match (true) {
                        $record->sip_expired_at === null => 'gray',
                        $record->isSipExpired() => 'danger',
                        $record->sip_expired_at->lte(now()->addDays($warningDays)) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('satusehat_ihs_id')->label('IHS ID')->placeholder('Belum')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(PractitionerType::class),
                Filter::make('sip_expiring')
                    ->label("SIP kedaluwarsa ≤ {$warningDays} hari")
                    ->query(fn (Builder $query): Builder => Practitioner::applySipExpiringWithin($query, $warningDays)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ]);
    }
}
