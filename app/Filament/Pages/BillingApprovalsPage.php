<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Support\NavGroup;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Billing\BillingApprovals;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use UnitEnum;

/**
 * Owner menyetujui/menolak void pembayaran, void tagihan, dan refund yang diajukan kasir.
 */
class BillingApprovalsPage extends Page
{
    protected string $view = 'filament.pages.billing-approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::BILLING;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Persetujuan void & refund';

    protected static ?string $title = 'Persetujuan void & refund';

    protected static ?string $slug = 'persetujuan';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Approve:Billing') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $count = self::pendingPayments()->count() + self::pendingInvoices()->count() + self::pendingRefunds()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** @return Collection<int, Payment> */
    public static function pendingPayments(): Collection
    {
        return Payment::query()->with(['invoice.patient', 'voidRequester', 'transaction'])
            ->where('status', PaymentStatus::VoidRequested)
            ->whereHas('invoice', fn ($q) => $q->whereIn('branch_id', BranchContext::available()->pluck('id')))
            ->orderBy('void_requested_at')->get();
    }

    /** @return Collection<int, Invoice> */
    public static function pendingInvoices(): Collection
    {
        return Invoice::query()->with(['patient', 'voidRequester'])
            ->whereNotNull('void_requested_at')->where('status', '!=', InvoiceStatus::Void)
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->orderBy('void_requested_at')->get();
    }

    /** @return Collection<int, Refund> */
    public static function pendingRefunds(): Collection
    {
        return Refund::query()->with(['patient', 'invoice', 'requester'])
            ->where('status', RefundStatus::Pending)
            ->whereIn('branch_id', BranchContext::available()->pluck('id'))
            ->orderBy('requested_at')->get();
    }

    public function decideAction(): Action
    {
        return Action::make('decide')
            ->label(fn (array $arguments): string => ($arguments['approve'] ?? false) ? 'Setujui' : 'Tolak')
            ->color(fn (array $arguments): string => ($arguments['approve'] ?? false) ? 'primary' : 'danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => (($arguments['approve'] ?? false) ? 'Setujui ' : 'Tolak ').match ($arguments['type'] ?? '') {
                'payment' => 'void pembayaran',
                'invoice' => 'void tagihan',
                default => 'refund',
            })
            ->schema(fn (array $arguments): array => ($arguments['type'] ?? '') === 'refund'
                ? [TextInput::make('note')->label('Catatan (opsional)')->maxLength(255)]
                : [])
            ->authorize(fn (): bool => static::canAccess())
            ->action(function (array $arguments, array $data): void {
                /** @var User $user */
                $user = auth()->user();
                $approve = (bool) ($arguments['approve'] ?? false);

                try {
                    match ($arguments['type'] ?? null) {
                        'payment' => BillingApprovals::decidePaymentVoid(Payment::findOrFail($arguments['id']), $approve, $user),
                        'invoice' => BillingApprovals::decideInvoiceVoid(Invoice::findOrFail($arguments['id']), $approve, $user),
                        'refund' => BillingApprovals::decideRefund(Refund::findOrFail($arguments['id']), $approve, $user, $data['note'] ?? null),
                        default => throw new InvalidArgumentException('Jenis pengajuan tidak dikenal.'),
                    };
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Belum bisa diputuskan')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($approve ? 'Disetujui' : 'Ditolak')->success()->send();
            });
    }
}
