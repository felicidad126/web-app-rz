<?php

namespace App\Filament\Resources\CashSessions\Pages;

use App\Enums\CashDifferenceReason;
use App\Enums\PaymentMethod;
use App\Filament\Resources\CashSessions\CashSessionResource;
use App\Filament\Support\MoneyInput;
use App\Models\CashSession;
use App\Models\User;
use App\Services\Billing\CashSessions;
use App\Support\Money;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/**
 * Tutup kasir (mockup "Tutup kasir"): cocokkan per metode bayar, hitung tunai per pecahan, catat selisih.
 *
 * @property-read Schema $form
 */
class CloseCashSession extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CashSessionResource::class;

    protected string $view = 'filament.billing.close-cash-session';

    protected static ?string $breadcrumb = 'Tutup kasir';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, int> */
    public array $system = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        /** @var CashSession $session */
        $session = $this->record;

        abort_unless($session->user_id === auth()->id() && (auth()->user()?->can('Update:CashSession') ?? false), 403);

        if (! $session->isOpen()) {
            $this->redirect(CashSessionResource::getUrl('view', ['record' => $session]));

            return;
        }

        $this->system = CashSessions::systemTotals($session);
        $this->form->fill([
            'denominations' => array_fill_keys(array_map('strval', config('clinic.billing.denominations')), null),
            'next_opening_cash' => $session->opening_cash,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Tutup kasir';
    }

    public function getSubheading(): ?string
    {
        /** @var CashSession $session */
        $session = $this->record;
        $session->loadMissing(['user', 'branch']);

        return 'Sesi '.$session->user->name.' · '.$session->opened_at->translatedFormat('l, d M Y').' · '.$session->opened_at->format('H:i').'–sekarang · '
            .$session->branch->name.' · '.$session->transactions()->count().' transaksi';
    }

    public function form(Schema $schema): Schema
    {
        /** @var CashSession $session */
        $session = $this->record;

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Group::make()->columnSpan(['lg' => 2])->schema([
                        Section::make('Cocokkan per metode bayar')
                            ->description('Tunai menurut sistem = kas awal '.Money::format($session->opening_cash).' + penerimaan tunai. Metode lain dicocokkan dengan laporan bank/EDC/merchant.')
                            ->schema($this->methodRows()),
                        Section::make('Catatan selisih')
                            ->description('Wajib bila ada selisih tunai atau metode yang tidak cocok.')
                            ->schema([
                                ToggleButtons::make('difference_reason')->hiddenLabel()->options(CashDifferenceReason::class)->inline(),
                                Textarea::make('notes')->hiddenLabel()->placeholder('Detail tambahan, mis. siapa yang menghitung dan kapan dicek ulang')->rows(2),
                            ]),
                        View::make('filament.billing.session-voids')->viewData(['session' => $session]),
                    ]),
                    Group::make()->schema([
                        Section::make('Hitung tunai per pecahan')
                            ->schema([
                                ...array_map(fn (int $value): Grid => Grid::make(2)->schema([
                                    Text::make(Money::format($value, false))->extraAttributes(['style' => 'padding-top: 0.5rem']),
                                    TextInput::make('denominations.'.$value)->hiddenLabel()->numeric()->minValue(0)->placeholder('lembar')->live(onBlur: true),
                                ]), config('clinic.billing.denominations')),
                                Grid::make(2)->schema([
                                    Text::make('Koin & sisa (Rp)')->extraAttributes(['style' => 'padding-top: 0.5rem']),
                                    TextInput::make('denominations.coins')->hiddenLabel()->numeric()->minValue(0)->placeholder('Rp')->live(onBlur: true),
                                ]),
                                Text::make(fn (Get $get): string => 'Total dihitung: '.Money::format($this->counted($get('denominations'))))->weight('bold'),
                            ]),
                        Section::make('Setelah ditutup')
                            ->schema([
                                MoneyInput::make('next_opening_cash')->label('Kas awal besok (tinggal di laci)')->minValue(0)->required()->live(onBlur: true),
                                Text::make(fn (Get $get): string => 'Disetor ke brankas: '.Money::format(max(0, $this->counted($get('denominations')) - (int) $get('next_opening_cash')))),
                            ]),
                    ]),
                ]),
            ]);
    }

    public function close(): void
    {
        $data = $this->form->getState();
        /** @var CashSession $session */
        $session = $this->record;
        /** @var User $user */
        $user = auth()->user();

        try {
            CashSessions::close($session, $user, [
                'denominations' => $data['denominations'] ?? [],
                'reported' => $data['reported'] ?? [],
                'difference_reason' => $data['difference_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'next_opening_cash' => (int) ($data['next_opening_cash'] ?? 0),
            ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Sesi belum bisa ditutup')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Sesi kasir ditutup & dikunci')->success()->send();
        $this->redirect(CashSessionResource::getUrl('view', ['record' => $session]));
    }

    /** @return list<Grid> */
    private function methodRows(): array
    {
        /** @var CashSession $session */
        $session = $this->record;
        $rows = [Grid::make(12)->schema([
            Text::make('Metode')->color('gray')->columnSpan(3),
            Text::make('Menurut sistem')->color('gray')->columnSpan(3),
            Text::make('Hasil hitung / laporan')->color('gray')->columnSpan(3),
            Text::make('Selisih')->color('gray')->columnSpan(3),
        ])];

        foreach (PaymentMethod::moneyIn() as $method) {
            $system = $method === PaymentMethod::Cash ? $session->opening_cash + ($this->system['cash'] ?? 0) : ($this->system[$method->value] ?? 0);

            $rows[] = Grid::make(12)->schema([
                Text::make($method->getLabel())->weight('bold')->columnSpan(3)->extraAttributes(['title' => $method->reconciliationSource()]),
                Text::make(Money::format($system, false))->columnSpan(3),
                $method === PaymentMethod::Cash
                    ? Text::make(fn (Get $get): string => Money::format($this->counted($get('denominations')), false))->columnSpan(3)
                    : TextInput::make('reported.'.$method->value)->hiddenLabel()->numeric()->placeholder($system === 0 ? '0' : $method->reconciliationSource())->live(onBlur: true)->columnSpan(3),
                Text::make(fn (Get $get): string => $this->differenceLabel($method, $system, $get))->columnSpan(3),
            ]);
        }

        return $rows;
    }

    private function differenceLabel(PaymentMethod $method, int $system, Get $get): string
    {
        if ($method === PaymentMethod::Cash) {
            $diff = $this->counted($get('denominations')) - $system;

            return $diff === 0 ? 'Cocok' : Money::format($diff, false);
        }

        $reported = $get('reported.'.$method->value);

        if (! is_numeric($reported)) {
            return $system === 0 ? '—' : 'belum dicocokkan';
        }

        $diff = (int) $reported - $system;

        return $diff === 0 ? 'Cocok' : Money::format($diff, false);
    }

    private function counted(mixed $denominations): int
    {
        return CashSessions::countDenominations(is_array($denominations) ? $denominations : []);
    }
}
