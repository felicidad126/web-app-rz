<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Invoices\RelationManagers\RefundsRelationManager;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Support\NavGroup;
use App\Models\Invoice;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InvoiceResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::BILLING;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tagihan';

    protected static ?string $pluralModelLabel = 'tagihan';

    protected static ?string $navigationLabel = 'Tagihan';

    protected static ?string $slug = 'tagihan';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Invoice ? $record->label() : null;
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    /** Hanya tagihan di cabang yang boleh diakses pengguna. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
    }

    /** Draf hanya bisa diubah; tagihan terbit dibuka di halaman detail. */
    public static function canEdit(Model $record): bool
    {
        /** @var Invoice $record */
        return $record->isDraft() && parent::canEdit($record);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
            RefundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/kasir'),
        ];
    }
}
