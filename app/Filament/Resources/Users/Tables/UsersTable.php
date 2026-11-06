<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->description(fn (User $record): string => $record->email)->searchable(['name', 'email'])->sortable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString()),
                TextColumn::make('branches.code')->label('Cabang')->badge()->color('gray')->visibleFrom('md'),
                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret) || $record->has_email_authentication)
                    ->boolean()
                    ->visibleFrom('md'),
                IconColumn::make('is_active')->label('Aktif')->boolean()->visibleFrom('sm'),
                TextColumn::make('last_login_at')->label('Masuk terakhir')->since()->placeholder('Belum pernah')->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('roles')->label('Peran')->relationship('roles', 'name'),
                TernaryFilter::make('is_active')->label('Aktif'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('resetMfa')
                        ->label('Reset MFA')
                        ->icon(Heroicon::OutlinedKey)
                        ->requiresConfirmation()
                        ->modalDescription('Pengguna wajib mengatur ulang MFA saat login berikutnya. Gunakan jika HP pengguna hilang atau ganti perangkat.')
                        ->visible(fn (User $record): bool => auth()->user()->can('update', $record) && ! $record->is(auth()->user()))
                        ->action(function (User $record): void {
                            $record->forceFill([
                                'app_authentication_secret' => null,
                                'app_authentication_recovery_codes' => null,
                                'has_email_authentication' => false,
                            ])->save();

                            activity('audit')->performedOn($record)->causedBy(auth()->user())->event('mfa_reset')->log('Reset MFA pengguna');

                            Notification::make()->title('MFA direset')->success()->send();
                        }),
                    RestoreAction::make(),
                ]),
            ]);
    }
}
