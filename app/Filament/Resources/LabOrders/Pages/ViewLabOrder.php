<?php

namespace App\Filament\Resources\LabOrders\Pages;

use App\Enums\LabOrderStatus;
use App\Filament\Clinic\CodeText;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\LabOrders\LabOrderResource;
use App\Models\LabOrder;
use App\Models\User;
use App\Services\Lab\LabOrders;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ViewLabOrder extends ViewRecord
{
    protected static string $resource = LabOrderResource::class;

    protected function resolveRecord(int|string $key): LabOrder
    {
        /** @var LabOrder $order */
        $order = LabOrders::withNextAppointment(LabOrderResource::getEloquentQuery()->select('lab_orders.*'))
            ->with(['patient', 'practitioner.user', 'supplier', 'branch', 'visit', 'visitProcedure.treatment', 'creator'])
            ->findOrFail($key);

        return $order;
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var LabOrder $order */
        $order = $this->getRecord();

        return $schema->columns(1)->components([
            View::make('filament.clinic.patient-banner')->viewData(['patient' => $order->patient]),
            View::make('filament.lab.order')->viewData(['order' => $order]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var LabOrder $order */
        $order = $this->getRecord();

        return 'Order lab · '.$order->patient->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var LabOrder $order */
        $order = $this->getRecord();

        return CodeText::join([[$order->number, true], $order->status->getLabel(), $order->branch->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            LabOrderResource::advanceAction()
                ->after(fn () => $this->redirect(LabOrderResource::getUrl('view', ['record' => $this->getRecord()]))),
            EditAction::make()->label('Ubah')->color('gray'),
            ActionGroup::make([
                Action::make('call')
                    ->label('Hubungi lab')
                    ->icon(Heroicon::OutlinedPhone)
                    ->visible(fn (LabOrder $record): bool => filled($record->supplier->phone))
                    ->url(fn (LabOrder $record): string => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $record->supplier->phone)),
                Action::make('schedule')
                    ->label('Jadwalkan pemasangan')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn (LabOrder $record): bool => in_array($record->status, LabOrderStatus::active(), true)
                        && (auth()->user()?->can('Create:Appointment') ?? false))
                    ->url(fn (LabOrder $record): string => AppointmentResource::getUrl('create', [
                        'patient' => $record->patient_id,
                        'practitioner' => $record->practitioner_id,
                        'date' => $record->due_at?->copy()->addDay()->toDateString(),
                    ])),
                Action::make('cancel')
                    ->label('Batalkan order')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (LabOrder $record): bool => in_array($record->status, LabOrderStatus::active(), true))
                    ->authorize(fn (LabOrder $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(5)])
                    ->action(function (LabOrder $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        try {
                            LabOrders::cancel($record, $data['reason'], $user);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Belum bisa dibatalkan')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Order lab dibatalkan')->success()->send();
                        $this->redirect(LabOrderResource::getUrl('view', ['record' => $record]));
                    }),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }
}
