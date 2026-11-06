<?php

namespace App\Filament\Pages;

use App\Enums\SatuSehatIssue;
use App\Enums\SatuSehatSyncStatus;
use App\Filament\Support\NavGroup;
use App\Models\SatuSehatSync;
use App\Models\Visit;
use App\Services\SatuSehat\SatuSehatClient;
use App\Services\SatuSehat\SatuSehatException;
use App\Services\SatuSehat\VisitSync;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Monitoring bridging SATUSEHAT (mockup "SATUSEHAT"): yang perlu diperbaiki, rekap per jenis data, log pengiriman.
 */
class SatuSehatMonitor extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.pages.satusehat-monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::OPERATIONS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'SATUSEHAT';

    protected static ?string $title = 'SATUSEHAT';

    protected static ?string $slug = 'satusehat';

    /** Jenis resource yang direkap, dengan label untuk staf. */
    public const RESOURCE_LABELS = [
        'Encounter' => 'Kunjungan',
        'Condition' => 'Diagnosis',
        'Observation' => 'Pemeriksaan',
        'Procedure' => 'Tindakan',
        'MedicationRequest' => 'Resep',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:SatuSehat') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $count = self::problemQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSubheading(): ?string
    {
        $env = config('satusehat.environment') === 'production' ? 'lingkungan produksi' : 'lingkungan sandbox (uji coba)';
        $mode = VisitSync::enabled() ? 'dikirim otomatis setelah rekam medis ditandatangani' : 'pengiriman otomatis belum aktif';

        return $env.' · '.$mode;
    }

    /** @return Builder<SatuSehatSync> */
    public static function problemQuery(): Builder
    {
        return SatuSehatSync::query()
            ->whereIn('status', [SatuSehatSyncStatus::Failed, SatuSehatSyncStatus::Blocked])
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', BranchContext::available()->pluck('id')));
    }

    /** @return EloquentCollection<int, SatuSehatSync> */
    public function problems(): EloquentCollection
    {
        return self::problemQuery()->with('visit.patient')->orderByDesc('updated_at')->limit(20)->get();
    }

    /** @return EloquentCollection<int, SatuSehatSync> */
    public function recent(): EloquentCollection
    {
        return SatuSehatSync::query()->with('visit.patient')
            ->where('status', SatuSehatSyncStatus::Sent)
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->orderByDesc('sent_at')->limit(8)->get();
    }

    /**
     * Rekap hari ini per jenis data: terkirim / antre / gagal (jumlah resource).
     *
     * @return Collection<string, array{label: string, sent: int, pending: int, failed: int}>
     */
    public function todayByType(): Collection
    {
        $rows = collect(self::RESOURCE_LABELS)->map(fn (string $label): array => ['label' => $label, 'sent' => 0, 'pending' => 0, 'failed' => 0]);

        $syncs = SatuSehatSync::query()
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->whereDate('updated_at', today())
            ->get(['status', 'resource_counts']);

        foreach ($syncs as $sync) {
            $bucket = match ($sync->status) {
                SatuSehatSyncStatus::Sent => 'sent',
                SatuSehatSyncStatus::Pending => 'pending',
                default => 'failed',
            };

            foreach ($sync->resource_counts ?? ['Encounter' => 1] as $type => $count) {
                if ($rows->has($type)) {
                    $row = $rows->get($type);
                    $row[$bucket] += (int) $count;
                    $rows->put($type, $row);
                }
            }
        }

        return $rows;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Semua pengiriman')
            ->query(fn (): Builder => SatuSehatSync::query()
                ->with(['visit.patient', 'branch'])
                ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhereIn('branch_id', BranchContext::available()->pluck('id'))))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('updated_at')->label('Terakhir')->dateTime('d/m H:i')->sortable(),
                TextColumn::make('visit.patient.name')->label('Pasien')
                    ->description(fn (SatuSehatSync $record): ?string => $record->visit?->visit_number)
                    ->searchable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (SatuSehatSync $record): ?string => $record->issue_code?->title()),
                TextColumn::make('attempts')->label('Percobaan')->alignCenter()->visibleFrom('md'),
                TextColumn::make('satusehat_id')->label('ID SATUSEHAT')->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 4).'…'.substr($state, -4) : '—')
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('xl'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(SatuSehatSyncStatus::class)->multiple(),
                SelectFilter::make('issue_code')->label('Penyebab')->options(collect(SatuSehatIssue::cases())->mapWithKeys(fn (SatuSehatIssue $i): array => [$i->value => $i->title()])->all()),
            ])
            ->recordActions([$this->resendTableAction()])
            ->toolbarActions([
                BulkAction::make('resendSelected')
                    ->label('Kirim ulang yang dipilih')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize(fn (): bool => self::canResend())
                    ->action(fn (EloquentCollection $records) => $this->resend($records)),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Tes koneksi')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->authorize(fn (): bool => self::canResend())
                ->action(function (): void {
                    $client = SatuSehatClient::fromConfig();

                    try {
                        $client->token(fresh: true);
                        $organization = $client->get('Organization/'.$client->organizationId());
                    } catch (SatuSehatException $e) {
                        Notification::make()->title($e->issue->title())->body($e->getMessage().' '.$e->issue->fix())->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title('Terhubung ke SATUSEHAT')
                        ->body('Organisasi: '.(data_get($organization, 'name') ?: $client->organizationId()))
                        ->success()->send();
                }),
            Action::make('resendProblems')
                ->label('Kirim ulang semua yang bermasalah')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => self::problemQuery()->exists())
                ->authorize(fn (): bool => self::canResend())
                ->requiresConfirmation()
                ->action(fn () => $this->resend(self::problemQuery()->get())),
            Action::make('backfill')
                ->label('Kirim kunjungan lama')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->authorize(fn (): bool => self::canResend())
                ->modalDescription('Kunjungan yang sudah ditandatangani sejak tanggal ini dan belum diterima SATUSEHAT akan dimasukkan ke antrean.')
                ->schema([DatePicker::make('since')->label('Sejak tanggal')->native(false)->displayFormat('d/m/Y')->default(today()->subDays(30))->required()])
                ->action(function (array $data): void {
                    $count = self::backfill($data['since']);
                    Notification::make()->title($count.' kunjungan masuk antrean SATUSEHAT')->success()->send();
                }),
        ];
    }

    /** Masukkan kunjungan bertanda tangan sejak tanggal tertentu yang belum diterima ke antrean. */
    public static function backfill(mixed $since): int
    {
        if (! VisitSync::enabled()) {
            return 0;
        }

        $count = 0;

        Visit::query()
            ->whereDate('visit_date', '>=', $since)
            ->whereHas('medicalRecord', fn (Builder $q) => $q->whereNotNull('signed_at'))
            ->whereDoesntHave('satusehatSyncs', fn (Builder $q) => $q->where('status', SatuSehatSyncStatus::Sent))
            ->orderBy('id')
            ->each(function (Visit $visit) use (&$count): void {
                VisitSync::queue($visit);
                $count++;
            });

        return $count;
    }

    public static function canResend(): bool
    {
        return auth()->user()?->can('Resend:SatuSehat') ?? false;
    }

    public function resendAction(): Action
    {
        return Action::make('resend')
            ->label('Kirim ulang')
            ->size('sm')
            ->color('gray')
            ->authorize(fn (): bool => self::canResend())
            ->action(fn (array $arguments) => $this->resend(SatuSehatSync::query()->whereKey($arguments['sync'] ?? 0)->get()));
    }

    /** Kiriman yang ternyata sudah ada di SATUSEHAT (duplikat) ditandai diterima secara manual, tercatat di audit. */
    public function markSentAction(): Action
    {
        return Action::make('markSent')
            ->label('Tandai sudah diterima')
            ->size('sm')
            ->color('gray')
            ->authorize(fn (): bool => self::canResend())
            ->requiresConfirmation()
            ->modalDescription('Pastikan kunjungan ini memang sudah ada di portal SATUSEHAT. Tindakan ini tercatat di log audit.')
            ->action(function (array $arguments): void {
                $sync = SatuSehatSync::query()->whereKey($arguments['sync'] ?? 0)->where('issue_code', SatuSehatIssue::Duplicate)->firstOrFail();
                $sync->forceFill([
                    'status' => SatuSehatSyncStatus::Sent,
                    'sent_at' => now(),
                    'last_error' => 'Ditandai sudah diterima secara manual oleh '.auth()->user()?->name.'.',
                ])->save();

                activity('audit')->performedOn($sync)->causedBy(auth()->user())->event('marked_sent')->log('Kiriman SATUSEHAT ditandai sudah diterima (duplikat)');
                Notification::make()->title('Ditandai sudah diterima')->success()->send();
            });
    }

    private function resendTableAction(): Action
    {
        return Action::make('resendRow')
            ->label('Kirim ulang')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (SatuSehatSync $record): bool => $record->status !== SatuSehatSyncStatus::Sent)
            ->authorize(fn (): bool => self::canResend())
            ->action(fn (SatuSehatSync $record) => $this->resend(new EloquentCollection([$record])));
    }

    /** @param EloquentCollection<int, SatuSehatSync> $syncs */
    private function resend(EloquentCollection $syncs): void
    {
        if (! VisitSync::enabled()) {
            Notification::make()->title('Pengiriman SATUSEHAT belum aktif')->body('Atur SATUSEHAT_ENABLED=true di .env server.')->warning()->send();

            return;
        }

        $count = 0;

        foreach ($syncs as $sync) {
            $visit = $sync->syncable_type === (new Visit)->getMorphClass() ? Visit::find($sync->syncable_id) : null;

            if ($visit !== null && $sync->status !== SatuSehatSyncStatus::Sent) {
                VisitSync::queue($visit);
                $count++;
            }
        }

        Notification::make()->title($count.' data masuk antrean kirim ulang')->success()->send();
    }
}
