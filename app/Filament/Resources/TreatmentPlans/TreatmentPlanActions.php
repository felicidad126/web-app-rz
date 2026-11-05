<?php

namespace App\Filament\Resources\TreatmentPlans;

use App\Enums\TreatmentPlanStatus;
use App\Models\TreatmentPlan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Perpindahan status rencana: draf → diajukan → disetujui / ditolak. */
final class TreatmentPlanActions
{
    public static function present(): Action
    {
        return Action::make('presentPlan')
            ->label('Ajukan ke pasien')
            ->icon(Heroicon::OutlinedPresentationChartLine)
            ->color('info')
            ->visible(fn (?TreatmentPlan $record): bool => $record?->status === TreatmentPlanStatus::Draft)
            ->authorize(fn (?TreatmentPlan $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(fn (TreatmentPlan $record) => $record->update(['status' => TreatmentPlanStatus::Presented, 'presented_at' => now()]));
    }

    public static function accept(): Action
    {
        return Action::make('acceptPlan')
            ->label('Disetujui pasien')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Item yang disetujui akan muncul sebagai pilihan saat mencatat tindakan di pemeriksaan, dan di daftar "rencana belum dijadwalkan".')
            ->visible(fn (?TreatmentPlan $record): bool => in_array($record?->status, [TreatmentPlanStatus::Draft, TreatmentPlanStatus::Presented], true))
            ->authorize(fn (?TreatmentPlan $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(function (TreatmentPlan $record): void {
                $record->update([
                    'status' => TreatmentPlanStatus::Accepted,
                    'presented_at' => $record->presented_at ?? now(),
                    'decided_at' => now(),
                ]);
                Notification::make()->title('Rencana disetujui pasien')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectPlan')
            ->label('Ditolak pasien')
            ->icon(Heroicon::OutlinedHandThumbDown)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (?TreatmentPlan $record): bool => $record?->status === TreatmentPlanStatus::Presented)
            ->authorize(fn (?TreatmentPlan $record): bool => $record !== null && (auth()->user()?->can('update', $record) ?? false))
            ->action(fn (TreatmentPlan $record) => $record->update(['status' => TreatmentPlanStatus::Rejected, 'decided_at' => now()]));
    }

    /** @return list<Action> */
    public static function all(): array
    {
        return [self::present(), self::accept(), self::reject()];
    }
}
