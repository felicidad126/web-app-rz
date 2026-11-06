<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PatientPayersRelationManager extends RelationManager
{
    protected static string $relationship = 'patientPayers';

    protected static ?string $title = 'Kepesertaan penjamin';

    protected static ?string $modelLabel = 'kepesertaan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payer_id')
                    ->label('Penjamin')
                    ->relationship('payer', 'name', fn ($query) => $query->where('is_active', true))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('patient_id', $this->getOwnerRecord()->getKey()))
                    ->preload()
                    ->required(),
                TextInput::make('member_number')
                    ->label('No. kartu / polis')
                    ->maxLength(255),
                DatePicker::make('valid_until')
                    ->label('Berlaku sampai')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('member_number')
            ->columns([
                TextColumn::make('payer.name')->label('Penjamin'),
                TextColumn::make('payer.type')->label('Jenis')->badge(),
                TextColumn::make('member_number')->label('No. kartu / polis')->placeholder('-'),
                TextColumn::make('valid_until')
                    ->label('Berlaku sampai')
                    ->date('d/m/Y')
                    ->color(fn ($state) => $state?->isPast() ? 'danger' : null)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah penjamin'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
