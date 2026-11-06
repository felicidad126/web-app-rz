<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Enums\TreatmentPlanStatus;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanActions;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EditTreatmentPlan extends EditRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    /** Isi rencana hanya bisa diubah sebelum pasien memutuskan. */
    public function isLocked(): bool
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return ! in_array($plan->status, [TreatmentPlanStatus::Draft, TreatmentPlanStatus::Presented], true);
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled($this->isLocked());
    }

    protected function getFormActions(): array
    {
        return $this->isLocked() ? [] : parent::getFormActions();
    }

    protected function getHeaderActions(): array
    {
        return TreatmentPlanActions::all();
    }

    /** Rencana yang sudah diputuskan pasien hanya dibaca: judulnya bukan "Ubah …". */
    public function getTitle(): string|Htmlable
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return $this->isLocked() ? 'Rencana · '.$plan->title : parent::getTitle();
    }

    public function getSubheading(): ?string
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return $plan->status->getLabel()
            .($this->isLocked() ? ' · isi rencana tidak bisa diubah lagi; status item diperbarui otomatis saat tindakan ditandatangani.' : '');
    }

    /** Banner pasien (alergi, penjamin, saldo terutang) di atas form, seperti halaman klinis lain. */
    public function content(Schema $schema): Schema
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->getRecord();

        return $schema->components([
            View::make('filament.clinic.patient-banner')->viewData(['patient' => $plan->patient]),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }
}
