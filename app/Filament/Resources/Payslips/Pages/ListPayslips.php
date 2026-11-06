<?php

namespace App\Filament\Resources\Payslips\Pages;

use App\Filament\Resources\Payslips\PayslipResource;
use App\Models\User;
use App\Services\Payroll\Payslips;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ListPayslips extends ListRecords
{
    protected static string $resource = PayslipResource::class;

    public function getSubheading(): ?string
    {
        return 'Komponen tetap tiap karyawan diatur di Pengguna → (karyawan) → Komponen gaji. Jasa dokter dibayar lewat Rekap jasa dokter.';
    }

    protected function getHeaderActions(): array
    {
        $period = fn (): Select => Select::make('period')->label('Periode')->options(PayslipResource::periodOptions())
            ->default(now()->startOfMonth()->toDateString())->required();

        return [
            Action::make('excel')
                ->label('Unduh rekap Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([$period()])
                ->action(fn (array $data) => $this->redirect(route('payroll.payslip-excel', ['period' => substr($data['period'], 0, 7)]))),
            Action::make('generate')
                ->label('Buat slip bulan ini')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (): bool => auth()->user()?->can('Manage:Payroll') ?? false)
                ->modalDescription('Membuat slip draf untuk karyawan aktif yang punya komponen gaji. Slip yang sudah ada tidak diubah.')
                ->schema([$period()])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        $created = Payslips::generate(Carbon::parse($data['period']), $user);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Slip belum dibuat')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title($created->isEmpty() ? 'Tidak ada slip baru' : $created->count().' slip draf dibuat')->success()->send();
                }),
        ];
    }
}
