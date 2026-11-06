<?php

namespace App\Filament\Resources\Commissions\Pages;

use App\Filament\Resources\Commissions\CommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    public function getSubheading(): ?string
    {
        return 'Satu baris per tindakan per peran. Void/refund tagihan membuat baris pembalik; angka yang sudah tercatat tidak diubah.';
    }
}
