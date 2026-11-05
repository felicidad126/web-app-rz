<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\TreatmentCategory;
use App\Filament\Clinic\PatientSelect;
use App\Filament\Clinic\PractitionerOptions;
use App\Models\Appointment;
use App\Models\Chair;
use App\Services\Clinic\AppointmentAvailability;
use App\Support\BranchContext;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Form janji temu (dipakai di halaman Janji temu dan modal di papan Jadwal).
 *
 * Form menyimpan tanggal, jam mulai, dan durasi; model menyimpan start_at & end_at.
 */
class AppointmentForm
{
    public const DURATIONS = [15 => '15 menit', 30 => '30 menit', 45 => '45 menit', 60 => '1 jam', 90 => '1,5 jam', 120 => '2 jam', 180 => '3 jam'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::components());
    }

    /** @return array<int, mixed> */
    public static function components(): array
    {
        return [
            PatientSelect::make()->columnSpanFull(),
            Select::make('branch_id')
                ->label('Cabang')
                ->options(fn (): array => BranchContext::options())
                ->default(fn (): ?int => BranchContext::id())
                ->live()
                ->required(),
            Select::make('practitioner_id')
                ->label('Dokter')
                ->options(fn (): array => PractitionerOptions::dentists())
                ->searchable()
                ->required(),
            DatePicker::make('date')
                ->label('Tanggal')
                ->native(false)
                ->displayFormat('D, d/m/Y')
                ->default(today())
                ->required(),
            TimePicker::make('start_time')
                ->label('Jam mulai')
                ->seconds(false)
                ->minutesStep(5)
                ->required(),
            Select::make('duration')
                ->label('Durasi')
                ->options(self::DURATIONS)
                ->default(30)
                ->required(),
            Select::make('chair_id')
                ->label('Kursi')
                ->options(fn (Get $get): array => Chair::query()
                    ->where('branch_id', $get('branch_id'))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->placeholder('Belum ditentukan'),
            Select::make('category')
                ->label('Jenis perawatan')
                ->options(TreatmentCategory::class),
            TextInput::make('reason')
                ->label('Rencana / keluhan')
                ->placeholder('Mis. tambal 36, kontrol behel')
                ->maxLength(255),
            Textarea::make('notes')
                ->label('Catatan')
                ->rows(2)
                ->columnSpanFull(),
            Checkbox::make('allow_outside_schedule')
                ->label('Tetap simpan walau di luar jadwal praktik dokter')
                ->dehydrated()
                ->columnSpanFull(),
        ];
    }

    /** @return array<string, mixed> Isi form dari janji temu yang ada. */
    public static function fromRecord(Appointment $appointment): array
    {
        return [
            ...$appointment->only(['patient_id', 'branch_id', 'practitioner_id', 'chair_id', 'reason', 'notes']),
            'category' => $appointment->category,
            'date' => $appointment->start_at->toDateString(),
            'start_time' => $appointment->start_at->format('H:i'),
            'duration' => (int) $appointment->start_at->diffInMinutes($appointment->end_at),
        ];
    }

    /**
     * Validasi bentrok & jadwal, lalu ubah isi form menjadi atribut model.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function toAttributes(array $data, ?int $ignoreAppointmentId = null, string $statePrefix = 'data.'): array
    {
        $start = Carbon::parse($data['date'])->setTimeFromTimeString((string) $data['start_time']);
        $end = $start->copy()->addMinutes((int) $data['duration']);

        $conflicts = AppointmentAvailability::conflicts(
            (int) $data['practitioner_id'],
            filled($data['chair_id'] ?? null) ? (int) $data['chair_id'] : null,
            (int) $data['patient_id'],
            $start,
            $end,
            $ignoreAppointmentId,
        );

        if ($conflicts !== []) {
            throw ValidationException::withMessages([$statePrefix.'start_time' => implode(' ', $conflicts)]);
        }

        $issue = AppointmentAvailability::scheduleIssue((int) $data['practitioner_id'], (int) $data['branch_id'], $start, $end);

        if ($issue !== null && ! ($data['allow_outside_schedule'] ?? false)) {
            throw ValidationException::withMessages([$statePrefix.'start_time' => $issue.' Centang "tetap simpan" jika memang disengaja.']);
        }

        return [
            'patient_id' => $data['patient_id'],
            'branch_id' => $data['branch_id'],
            'practitioner_id' => $data['practitioner_id'],
            'chair_id' => $data['chair_id'] ?? null,
            'category' => $data['category'] ?? null,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'start_at' => $start,
            'end_at' => $end,
        ];
    }
}
