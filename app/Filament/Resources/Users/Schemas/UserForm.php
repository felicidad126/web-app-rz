<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Akun')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Alamat email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('No. HP')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('password')
                            ->label(fn (string $operation): string => $operation === 'edit' ? 'Kata sandi baru' : 'Kata sandi')
                            ->password()
                            ->revealable()
                            ->rule(Password::min(10)->letters()->numbers())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Kosongkan jika tidak ingin mengganti.' : 'Minimal 10 karakter, kombinasi huruf dan angka.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Pengguna nonaktif tidak bisa masuk.')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                    ]),
                Section::make('Peran')
                    ->columnSpan(1)
                    ->components([
                        CheckboxList::make('roles')
                            ->hiddenLabel()
                            ->relationship('roles', 'name', function (Builder $query): Builder {
                                if (! auth()->user()?->hasRole(config('filament-shield.super_admin.name'))) {
                                    $query->where('name', '!=', config('filament-shield.super_admin.name'));
                                }

                                return $query->orderBy('name');
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record): string => str($record->name)->headline()->toString())
                            ->required(),
                    ]),
                Section::make('Cabang')
                    ->columnSpan(1)
                    ->components([
                        CheckboxList::make('branches')
                            ->hiddenLabel()
                            ->relationship('branches', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->helperText('Cabang tempat pengguna bertugas.'),
                    ]),
            ]);
    }
}
