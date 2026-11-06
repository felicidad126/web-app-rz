<?php

namespace App\Filament\Resources\PractitionerAttendances\Pages;

use App\Enums\AttendanceType;
use App\Filament\Resources\PractitionerAttendances\PractitionerAttendanceResource;
use App\Models\Practitioner;
use App\Models\User;
use App\Services\Commission\Attendances;
use App\Support\BranchContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ManagePractitionerAttendances extends ManageRecords
{
    protected static string $resource = PractitionerAttendanceResource::class;

    public function getSubheading(): ?string
    {
        return 'Uang hadir & on call masuk ke rekap jasa bulanan dokter. Catatan yang sudah direkap tidak bisa diubah.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromVisits')
                ->label('Isi dari kunjungan')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('Create:PractitionerAttendance') ?? false)
                ->modalDescription('Mencatat "Hadir" untuk setiap dokter yang punya kunjungan selesai di cabang & tanggal itu (yang sudah tercatat dilewati).')
                ->schema([
                    Select::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options())->default(fn (): ?int => BranchContext::id())->required(),
                    DatePicker::make('date')->label('Tanggal')->native(false)->displayFormat('d/m/Y')->default(today())->maxDate(today())->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $count = Attendances::fillFromVisits((int) $data['branch_id'], Carbon::parse($data['date']), $user);
                    Notification::make()->title($count > 0 ? $count.' kehadiran dicatat' : 'Tidak ada kehadiran baru')->success()->send();
                }),
            CreateAction::make()
                ->label('Catat kehadiran')
                ->using(function (array $data): Model {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        return Attendances::record(
                            Practitioner::findOrFail($data['practitioner_id']),
                            (int) $data['branch_id'],
                            Carbon::parse($data['date']),
                            $data['type'] instanceof AttendanceType ? $data['type'] : AttendanceType::from($data['type']),
                            (int) $data['amount'],
                            $user,
                            $data['notes'] ?? null,
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Belum tersimpan')->body($e->getMessage())->danger()->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
