<?php

namespace App\Filament\Resources\LabOrders;

use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\LabOrders\Pages\CreateLabOrder;
use App\Filament\Resources\LabOrders\Pages\EditLabOrder;
use App\Filament\Resources\LabOrders\Pages\ListLabOrders;
use App\Filament\Resources\LabOrders\Pages\ViewLabOrder;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavGroup;
use App\Models\LabOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitProcedure;
use App\Services\Lab\LabOrders;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use UnitEnum;

class LabOrderResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = LabOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::OPERATIONS;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'order-lab';

    protected static ?string $modelLabel = 'order lab';

    protected static ?string $pluralModelLabel = 'order lab';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('lab_orders.branch_id', BranchContext::available()->pluck('id'));
    }

    public static function canEdit(Model $record): bool
    {
        /** @var LabOrder $record */
        return in_array($record->status, LabOrderStatus::active(), true) && parent::canEdit($record);
    }

    public static function getNavigationBadge(): ?string
    {
        $late = static::getEloquentQuery()->whereIn('status', [LabOrderStatus::Created, LabOrderStatus::Sent])
            ->whereDate('due_at', '<', today())->count();

        return $late > 0 ? (string) $late : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Order lab terlambat';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pasien & dokter')
                    ->columns(2)
                    ->components([
                        PatientSelect::make()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('visit_id', null);
                                $set('visit_procedure_id', null);
                            })
                            ->disabledOn('edit')
                            ->columnSpanFull(),
                        Select::make('visit_id')
                            ->label('Kunjungan')
                            ->helperText('Opsional. Kunjungan saat cetak/preparasi.')
                            ->options(fn (Get $get): array => self::visitOptions($get('patient_id')))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('visit_procedure_id', null);

                                if ($state && ($visit = Visit::find($state))) {
                                    $set('practitioner_id', $visit->practitioner_id);
                                    $set('branch_id', $visit->branch_id);
                                }
                            }),
                        Select::make('visit_procedure_id')
                            ->label('Tindakan')
                            ->options(fn (Get $get): array => self::procedureOptions($get('visit_id')))
                            ->visible(fn (Get $get): bool => filled($get('visit_id'))),
                        Select::make('practitioner_id')
                            ->label('Dokter')
                            ->options(fn (): array => PractitionerOptions::dentists())
                            ->default(fn (): ?int => auth()->user()?->practitioner?->id)
                            ->searchable()
                            ->required(),
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->options(fn (): array => BranchContext::options())
                            ->default(fn (): ?int => BranchContext::id())
                            ->disabledOn('edit')
                            ->required(),
                    ]),
                Section::make('Pekerjaan')
                    ->columns(2)
                    ->components([
                        Select::make('supplier_id')
                            ->label('Lab')
                            ->options(fn (): array => Supplier::where('is_lab', true)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')->label('Nama lab')->required()->maxLength(255),
                                TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
                            ])
                            ->createOptionUsing(fn (array $data): int => Supplier::create([...$data, 'is_lab' => true])->id),
                        Select::make('work_type')
                            ->label('Jenis pekerjaan')
                            ->options(LabWorkType::class)
                            ->required(),
                        Textarea::make('specification')
                            ->label('Spesifikasi')
                            ->placeholder('Contoh: PFM, margin chamfer, kontak proksimal rapat')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('teeth')
                            ->label('Gigi')
                            ->placeholder('Contoh: 36 atau 14–16')
                            ->maxLength(60),
                        TextInput::make('shade')
                            ->label('Warna (shade)')
                            ->placeholder('Contoh: A2')
                            ->maxLength(30),
                        MoneyInput::make('cost')
                            ->label('Biaya lab')
                            ->helperText('Ikut tercatat di tagihan untuk margin dan jasa dokter.')
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        DatePicker::make('due_at')
                            ->label('Janji selesai dari lab')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => LabOrders::withNextAppointment(
                $query->select('lab_orders.*')->with(['patient', 'practitioner.user', 'supplier']),
            ))
            ->defaultSort(fn (Builder $query) => LabOrders::orderByUrgency($query))
            ->columns([
                TextColumn::make('patient.name')->label('Pasien')
                    ->description(fn (LabOrder $record): string => $record->practitioner->user->name)
                    ->searchable(),
                TextColumn::make('work_type')->label('Pekerjaan · lab')
                    ->formatStateUsing(fn (LabOrder $record): string => $record->work_type->getLabel().($record->teeth ? ' '.$record->teeth : ''))
                    ->description(fn (LabOrder $record): string => $record->supplier->name)
                    ->visibleFrom('md'),
                // Terlambat = perlu ditindaklanjuti (amber); merah hanya untuk peringatan medis.
                TextColumn::make('due_at')->label('Jatuh tempo')->date('d/m/Y')->placeholder('—')
                    ->description(fn (LabOrder $record): ?string => self::dueNote($record))
                    ->color(fn (LabOrder $record): ?string => $record->isLate() ? 'warning' : null),
                TextColumn::make('next_appointment_at')->label('Janji pasang')->dateTime('d/m/Y H:i')
                    ->placeholder('Belum dijadwalkan')
                    ->description(fn (LabOrder $record): ?string => self::appointmentWarning($record))
                    ->color(fn (LabOrder $record): ?string => self::appointmentWarning($record) ? 'warning' : null)
                    ->visibleFrom('lg'),
                TextColumn::make('status')->label('Tahap')->badge()->extraAttributes(['class' => 'klinik-status'])
                    ->description(fn (LabOrder $record): ?string => $record->status->step() > 0 ? 'Tahap '.$record->status->step().' dari 5' : null),
                TextColumn::make('cost')->label('Biaya lab')->alignEnd()
                    ->formatStateUsing(fn ($state): string => Money::format((int) $state, false))
                    ->visibleFrom('lg'),
                TextColumn::make('number')->label('No.')->fontFamily(FontFamily::Mono)->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label('Lab')
                    ->options(fn (): array => Supplier::where('is_lab', true)->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::dentists()),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())
                    ->visible(fn (): bool => count(BranchContext::options()) > 1),
            ])
            ->emptyStateHeading('Tidak ada order lab')
            ->recordActions([
                // Di tabel aksi per baris abu-abu; tombol teal hanya di halaman detail order.
                self::advanceAction()->color('gray'),
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function advanceAction(): Action
    {
        return Action::make('advance')
            ->label(fn (LabOrder $record): string => $record->status->next()?->advanceLabel() ?? 'Maju')
            ->icon(Heroicon::OutlinedArrowRight)
            ->color('primary')
            ->visible(fn (LabOrder $record): bool => $record->status->next() !== null)
            ->authorize(fn (LabOrder $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (LabOrder $record): string => ($record->status->next()?->advanceLabel() ?? 'Maju').'?')
            ->modalDescription(fn (LabOrder $record): string => $record->patient->name.' · '.$record->work_type->getLabel().' · '.$record->supplier->name)
            ->action(function (LabOrder $record): void {
                /** @var User $user */
                $user = auth()->user();

                try {
                    LabOrders::advance($record, $user);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Belum bisa diproses')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Tahap: '.$record->status->getLabel())->success()->send();
            });
    }

    public static function dueNote(LabOrder $record): ?string
    {
        if ($record->due_at === null || ! in_array($record->status, [LabOrderStatus::Created, LabOrderStatus::Sent], true)) {
            return null;
        }

        $days = (int) today()->diffInDays($record->due_at, false);

        return match (true) {
            $days < 0 => 'terlambat '.abs($days).' hari',
            $days === 0 => 'hari ini',
            default => $days.' hari lagi',
        };
    }

    /** Janji pasang padahal pekerjaan lab belum kembali ke klinik: perlu dicek ulang. */
    public static function appointmentWarning(LabOrder $record): ?string
    {
        /** @var Carbon|null $appointment */
        $appointment = $record->getAttribute('next_appointment_at');

        if ($appointment === null || ! in_array($record->status, [LabOrderStatus::Created, LabOrderStatus::Sent], true)) {
            return null;
        }

        return match (true) {
            $record->due_at !== null && $appointment->lt($record->due_at) => 'sebelum lab selesai',
            $record->isLate() => 'lab belum kembali',
            default => null,
        };
    }

    /** @return array<int, string> */
    public static function visitOptions(mixed $patientId): array
    {
        if (blank($patientId)) {
            return [];
        }

        return Visit::query()->with('practitioner.user')->where('patient_id', $patientId)
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->latest('visit_date')->latest('id')->limit(20)->get()
            ->mapWithKeys(fn (Visit $v): array => [$v->id => $v->visit_number.' · '.$v->visit_date->format('d/m/Y').' · '.$v->practitioner->user->name])
            ->all();
    }

    /** @return array<int, string> */
    public static function procedureOptions(mixed $visitId): array
    {
        if (blank($visitId)) {
            return [];
        }

        return VisitProcedure::query()->with('treatment')->where('visit_id', $visitId)->get()
            ->mapWithKeys(fn (VisitProcedure $p): array => [$p->id => $p->treatment->name.($p->tooth_number ? ' · gigi '.$p->tooth_number : '')])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabOrders::route('/'),
            'create' => CreateLabOrder::route('/create'),
            'view' => ViewLabOrder::route('/{record}'),
            'edit' => EditLabOrder::route('/{record}/edit'),
        ];
    }
}
