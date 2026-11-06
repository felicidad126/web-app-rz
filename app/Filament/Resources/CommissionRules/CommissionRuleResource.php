<?php

namespace App\Filament\Resources\CommissionRules;

use App\Enums\CommissionBase;
use App\Enums\CommissionRecognition;
use App\Enums\CommissionRole;
use App\Enums\CommissionType;
use App\Enums\FeeGroup;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\CommissionRules\Pages\ManageCommissionRules;
use App\Filament\Support\NavGroup;
use App\Models\CommissionRule;
use App\Models\Treatment;
use App\Services\Commission\CommissionEngine;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/** Aturan jasa dokter/asisten (Planning §5.7). Aturan yang paling spesifik yang dipakai. */
class CommissionRuleResource extends Resource
{
    protected static ?string $model = CommissionRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::PAYROLL;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'aturan-jasa';

    protected static ?string $modelLabel = 'aturan jasa';

    protected static ?string $pluralModelLabel = 'aturan jasa';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nama aturan')->placeholder('Mis. Konsultasi 100%')->required()->maxLength(255)->columnSpanFull(),
                Section::make('Berlaku untuk')
                    ->description('Kosongkan untuk "semua". Bila beberapa aturan cocok, yang dipakai: dokter + tindakan → dokter + jenis jasa → dokter → tindakan → jenis jasa → default.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('role')->label('Peran')->options(CommissionRole::class)->default(CommissionRole::Operator)->required(),
                        Select::make('practitioner_id')->label('Dokter / nakes')->options(fn (): array => PractitionerOptions::all())->searchable()->placeholder('Semua'),
                        Select::make('fee_group')->label('Jenis jasa')->options(FeeGroup::class)->placeholder('Semua'),
                        Select::make('treatment_id')->label('Tindakan')
                            ->options(fn (): array => Treatment::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->placeholder('Semua'),
                    ]),
                Section::make('Perhitungan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('type')->label('Tipe')->options(CommissionType::class)->default(CommissionType::Percent)->live()->required(),
                        TextInput::make('percent')->label('Persen')->suffix('%')->numeric()->minValue(0)->maxValue(100)
                            ->visible(fn (Get $get): bool => self::isPercent($get('type')))
                            ->required(fn (Get $get): bool => self::isPercent($get('type'))),
                        TextInput::make('fixed_amount')->label('Nominal per tindakan')->prefix('Rp')->numeric()->minValue(0)
                            ->visible(fn (Get $get): bool => ! self::isPercent($get('type')))
                            ->required(fn (Get $get): bool => ! self::isPercent($get('type'))),
                        Select::make('base')->label('Basis')->options(CommissionBase::class)->default(CommissionBase::Net)
                            ->helperText('"Setelah diskon" = harga − diskon baris − bagian diskon tagihan, sebelum pajak. Bahan dari pemakaian stok, lab dari order lab.')
                            ->visible(fn (Get $get): bool => self::isPercent($get('type')))->required(),
                        Select::make('recognition')->label('Diakui')->options(CommissionRecognition::class)
                            ->default(CommissionRecognition::Performed)->required()
                            ->helperText('Keputusan owner: saat tindakan dikerjakan. Kontrol ortho dihitung per kunjungan dari nilai paket per kontrol.'),
                        Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                    ]),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Perubahan aturan hanya berlaku untuk tagihan yang terbit sesudahnya; aturan di-snapshot per baris tindakan.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['practitioner.user', 'treatment']))
            ->defaultSort('role')
            ->columns([
                TextColumn::make('name')->label('Aturan')->searchable()->wrap()
                    ->description(fn (CommissionRule $record): string => self::scopeLabel($record)),
                TextColumn::make('role')->label('Peran')->badge()->color('gray'),
                TextColumn::make('summary')->label('Perhitungan')->state(fn (CommissionRule $record): string => $record->summary())->wrap(),
                TextColumn::make('recognition')->label('Diakui')->visibleFrom('lg'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peran')->options(CommissionRole::class),
                SelectFilter::make('fee_group')->label('Jenis jasa')->options(FeeGroup::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Arsipkan'),
                RestoreAction::make(),
            ]);
    }

    public static function scopeLabel(CommissionRule $rule): string
    {
        $parts = array_filter([
            $rule->practitioner?->displayName(),
            $rule->treatment?->name,
            $rule->fee_group?->getLabel(),
        ]);

        return match (CommissionEngine::scope($rule)) {
            'default' => 'Default (semua tindakan, semua dokter)',
            default => implode(' · ', $parts),
        };
    }

    private static function isPercent(mixed $type): bool
    {
        return ($type instanceof CommissionType ? $type : CommissionType::tryFrom((string) $type)) !== CommissionType::Fixed;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCommissionRules::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
