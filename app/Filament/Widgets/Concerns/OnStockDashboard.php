<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\BranchContext;

/** Widget Dashboard edisi inventori: tampil bila pengguna boleh melihat stok dan punya cabang aktif. */
trait OnStockDashboard
{
    public static function canView(): bool
    {
        return (auth()->user()?->can('View:Stock') ?? false) && BranchContext::id() !== null;
    }

    protected function branchId(): int
    {
        return (int) BranchContext::id();
    }
}
