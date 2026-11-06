<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Enums\RecallStatus;
use App\Models\Treatment;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecallsRelationManager extends RelationManager
{
    protected static string $relationship = 'recalls';

    protected static ?string $title = 'Recall';

    protected static ?string $modelLabel = 'recall';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('ViewAny:Recall') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('reason')->label('Keperluan')->required()->maxLength(255),
            DatePicker::make('due_date')->label('Jatuh tempo')->native(false)->displayFormat('d/m/Y')->required(),
            Select::make('treatment_id')->label('Tindakan terkait')->options(fn (): array => Treatment::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('status')->label('Status')->options(RecallStatus::class)->default(RecallStatus::Pending)->required(),
            TextInput::make('notes')->label('Catatan')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->columns([
                TextColumn::make('due_date')->label('Jatuh tempo')->date('d/m/Y'),
                TextColumn::make('reason')->label('Keperluan'),
                TextColumn::make('status')->label('Status')->badge()->extraAttributes(['class' => 'klinik-status']),
                TextColumn::make('last_contacted_at')->label('Terakhir dihubungi')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Tambah recall')])
            ->recordActions([EditAction::make()]);
    }
}
