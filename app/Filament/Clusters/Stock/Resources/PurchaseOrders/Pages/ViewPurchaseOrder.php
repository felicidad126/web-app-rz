<?php

namespace App\Filament\Clusters\Stock\Resources\PurchaseOrders\Pages;

use App\Filament\Clusters\Stock\PurchaseActions;
use App\Filament\Clusters\Stock\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var PurchaseOrder $order */
        $order = $this->getRecord();
        $order->loadMissing([
            'branch', 'supplier', 'creator', 'lines.item', 'lines.unit', 'lines.purchaseRequest.requester',
            'receipts.creator', 'receipts.lines', 'cashEntries.creator',
        ]);

        return $schema->columns(1)->components([View::make('filament.stock.purchase-order')->viewData(['order' => $order])]);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var PurchaseOrder $order */
        $order = $this->getRecord();

        return 'Pesanan '.$order->number;
    }

    public function getSubheading(): ?string
    {
        /** @var PurchaseOrder $order */
        $order = $this->getRecord();

        return collect([
            $order->status->getLabel(),
            $order->supplier->name ?? 'Tanpa supplier',
            'dipesan '.$order->order_date->format('d/m/Y'),
            PurchaseOrderResource::arrivalLabel($order),
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        $refresh = fn (Action $action): Action => $action->after(fn () => $this->redirect(PurchaseOrderResource::getUrl('view', ['record' => $this->getRecord()])));

        return [
            $refresh(PurchaseActions::pay()),
            ActionGroup::make([$refresh(PurchaseActions::closeRemaining()), $refresh(PurchaseActions::cancelOrder())])
                ->label('Lainnya')->button()->color('gray'),
            $refresh(PurchaseActions::receive()),
        ];
    }
}
