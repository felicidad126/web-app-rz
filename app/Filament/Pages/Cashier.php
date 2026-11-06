<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\RefundStatus;
use App\Enums\VisitStatus;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Resources\CashSessions\CashSessionResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Support\MoneyInput;
use App\Filament\Support\NavGroup;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Refund;
use App\Models\User;
use App\Models\Visit;
use App\Services\Billing\CashSessions;
use App\Services\Billing\InvoiceBuilder;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use UnitEnum;

/**
 * Kasir: pasien hari ini yang sudah diperiksa, siap ditagih. Sesi kasir dibuka/ditutup dari sini.
 */
class Cashier extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.pages.cashier';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::BILLING;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Kasir';

    protected static ?string $title = 'Kasir';

    protected static ?string $slug = 'kasir';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ViewAny:Invoice') ?? false;
    }

    public function getSubheading(): ?string
    {
        $session = $this->session();
        $branch = BranchContext::available()->firstWhere('id', BranchContext::id())?->name;

        return $branch.' · '.today()->translatedFormat('l, d F Y').' · '.($session
            ? 'Sesi kasir '.$session->user->name.' dibuka '.$session->opened_at->format('H:i').' (kas awal '.Money::format($session->opening_cash).')'
            : 'sesi kasir Anda belum dibuka');
    }

    public function session(): ?CashSession
    {
        $user = auth()->user();

        return $user instanceof User ? CashSessions::current($user, BranchContext::id()) : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pasien hari ini')
            ->description('Pasien yang sudah diperiksa. Klik "Kasir" untuk membuat atau membuka tagihannya.')
            ->query(fn (): Builder => Visit::query()
                ->with(['patient', 'practitioner.user', 'medicalRecord', 'invoices'])
                ->where('branch_id', BranchContext::id())
                ->whereDate('visit_date', today())
                ->whereIn('status', [VisitStatus::InTreatment, VisitStatus::Done, VisitStatus::Billed]))
            ->defaultSort('queue_number')
            ->paginated(false)
            ->emptyStateHeading('Belum ada pasien selesai diperiksa hari ini')
            ->emptyStateDescription('Pasien muncul di sini setelah dokter selesai memeriksa. Untuk pembelian tanpa kunjungan, pakai "Penjualan langsung".')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->columns([
                TextColumn::make('queue_number')->label('No.')->alignCenter()->visibleFrom('sm'),
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (Visit $record): HtmlString => new HtmlString('<span class="klinik-mono">'.e($record->patient->rm_number.' · '.$record->visit_number).'</span>'))
                    ->searchable(),
                TextColumn::make('practitioner.user.name')->label('Dokter')->visibleFrom('md'),
                TextColumn::make('status')->label('Kunjungan')->badge()->extraAttributes(['class' => 'klinik-status'])->visibleFrom('md')
                    ->description(fn (Visit $record): ?string => $record->isLocked() ? null : 'RM belum ditandatangani'),
                TextColumn::make('invoice_status')
                    ->label('Tagihan')
                    ->badge()
                    ->extraAttributes(['class' => 'klinik-status'])
                    ->state(fn (Visit $record): ?InvoiceStatus => self::invoiceOf($record)?->status)
                    ->placeholder('Belum dibuat'),
                TextColumn::make('balance')
                    ->label('Sisa')
                    ->alignEnd()
                    ->state(fn (Visit $record): ?int => self::invoiceOf($record)?->balance())
                    ->formatStateUsing(fn (?int $state): string => Money::format($state, false))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('checkout')
                    ->label('Kasir')
                    ->icon(Heroicon::OutlinedCalculator)
                    // Satu tombol utama per layar: aksi baris abu-abu.
                    ->button()
                    ->color('gray')
                    ->authorize(fn (): bool => auth()->user()?->can('Create:Invoice') ?? false)
                    ->action(function (Visit $record, Action $action): void {
                        try {
                            $invoice = InvoiceBuilder::forVisit($record);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Tagihan belum bisa dibuat')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        $action->redirect(InvoiceResource::getUrl($invoice->isDraft() ? 'edit' : 'view', ['record' => $invoice]));
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSession')
                ->label('Buka sesi kasir')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (): bool => $this->session() === null && BranchContext::id() !== null)
                ->authorize(fn (): bool => auth()->user()?->can('Create:CashSession') ?? false)
                ->fillForm(fn (): array => ['opening_cash' => CashSessions::suggestedOpeningCash((int) BranchContext::id())])
                ->schema([MoneyInput::make('opening_cash')->label('Kas awal di laci')->minValue(0)->required()])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    CashSessions::open($user, (int) BranchContext::id(), (int) $data['opening_cash']);
                    Notification::make()->title('Sesi kasir dibuka')->success()->send();
                }),
            Action::make('closeSession')
                ->label('Tutup kasir')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->visible(fn (): bool => $this->session() !== null)
                ->url(fn (): string => CashSessionResource::getUrl('close', ['record' => $this->session()])),
            Action::make('directSale')
                ->label('Penjualan langsung')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('Create:Invoice') ?? false)
                ->modalDescription('Untuk pembelian tanpa kunjungan, mis. sikat gigi, obat, atau pelunasan.')
                ->schema([PatientSelect::make()])
                ->action(function (array $data, Action $action): void {
                    $invoice = InvoiceBuilder::blank(Patient::findOrFail($data['patient_id']), (int) BranchContext::id());
                    $action->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                }),
        ];
    }

    /** @return Collection<int, Refund> refund yang sudah disetujui owner, menunggu diserahkan */
    public function approvedRefunds(): Collection
    {
        return Refund::query()->with(['patient', 'invoice'])
            ->where('branch_id', BranchContext::id())
            ->where('status', RefundStatus::Approved)
            ->orderBy('decided_at')
            ->get();
    }

    private static function invoiceOf(Visit $visit): ?Invoice
    {
        return $visit->invoices->where('status', '!=', InvoiceStatus::Void)->sortByDesc('id')->first();
    }
}
