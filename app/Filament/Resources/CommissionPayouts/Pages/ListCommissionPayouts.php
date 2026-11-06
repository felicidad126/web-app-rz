<?php

namespace App\Filament\Resources\CommissionPayouts\Pages;

use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Resources\CommissionPayouts\CommissionPayoutResource;
use App\Models\User;
use App\Services\Commission\CommissionPayouts;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ListCommissionPayouts extends ListRecords
{
    protected static string $resource = CommissionPayoutResource::class;

    public function getSubheading(): ?string
    {
        return 'Basis jasa = harga setelah diskon (dikurangi bahan & biaya lab sesuai jenis jasa). Aturan & harga disimpan per tindakan saat tagihan terbit, jadi perubahan tarif tidak mengubah bulan yang sudah lewat. PPh 21 diisi manual sebagai potongan.';
    }

    protected function getHeaderActions(): array
    {
        $defaultPeriod = (today()->day <= 10 ? now()->subMonthNoOverflow() : now())->startOfMonth()->toDateString();

        return [
            Action::make('excel')
                ->label('Download Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([Select::make('period')->label('Periode')->options(CommissionPayoutResource::periodOptions())->default($defaultPeriod)->required()])
                ->action(fn (array $data) => $this->redirect(route('payroll.commission-excel', ['period' => substr($data['period'], 0, 7)]))),
            Action::make('generate')
                ->label('Buat / perbarui rekap')
                ->icon(Heroicon::OutlinedCalculator)
                ->visible(fn (): bool => auth()->user()?->can('Manage:Commission') ?? false)
                ->modalDescription('Memasukkan semua jasa yang sudah diakui s.d. akhir bulan dan belum direkap, termasuk susulan & pembalik, serta uang hadir/on call. Rekap draf dibuat ulang; rekap final/dibayar tidak berubah.')
                ->schema([
                    Select::make('period')->label('Periode')->options(CommissionPayoutResource::periodOptions())->default($defaultPeriod)->required(),
                    Select::make('practitioner_id')->label('Dokter')->options(fn (): array => PractitionerOptions::all())->placeholder('Semua dokter'),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        $payouts = CommissionPayouts::generate(Carbon::parse($data['period']), $user, filled($data['practitioner_id'] ?? null) ? (int) $data['practitioner_id'] : null);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Rekap belum dibuat')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title($payouts->isEmpty() ? 'Tidak ada jasa baru untuk direkap' : $payouts->count().' rekap draf diperbarui')
                        ->success()->send();
                }),
        ];
    }
}
