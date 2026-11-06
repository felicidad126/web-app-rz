<?php

namespace App\Filament\Resources\LabOrders\Pages;

use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Models\User;
use App\Models\Visit;
use App\Services\Lab\LabOrders;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateLabOrder extends CreateRecord
{
    protected static string $resource = LabOrderResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterFill(): void
    {
        // Dari halaman pemeriksaan: ?visit=..
        $visit = request()->integer('visit') ? Visit::find(request()->integer('visit')) : null;
        $prefill = array_filter([
            'patient_id' => request()->integer('patient') ?: $visit?->patient_id,
            'visit_id' => $visit?->id,
            'practitioner_id' => $visit?->practitioner_id,
            'branch_id' => $visit?->branch_id,
        ], fn ($value): bool => filled($value));

        if ($prefill !== []) {
            $this->form->fill([...$this->data ?? [], ...$prefill]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return LabOrders::create($data, $user);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Belum tersimpan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return LabOrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
