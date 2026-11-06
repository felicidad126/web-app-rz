<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Clinic\CodeText;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\BranchContext;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class InvoicesTable
{
    /** Kode (No. RM, No. kunjungan) dalam huruf mono. */
    /** Kode dalam huruf mono untuk deskripsi kolom (lihat CodeText). */
    public static function code(?string $code): HtmlString
    {
        return CodeText::code($code);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'branch', 'visit', 'installments']))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. tagihan')
                    ->placeholder('Draf')
                    ->fontFamily('mono')
                    ->description(fn (Invoice $record): string => ($record->issued_at ?? $record->created_at)->format('d/m/Y H:i'))
                    ->searchable(),
                TextColumn::make('patient.name')
                    ->label('Pasien')
                    ->description(fn (Invoice $record): HtmlString => self::code($record->patient->rm_number))
                    ->searchable(['name', 'rm_number']),
                TextColumn::make('source')
                    ->label('Asal')
                    ->state(fn (Invoice $record): string|HtmlString => $record->visit
                        ? self::code($record->visit->visit_number)
                        : ($record->installments->isNotEmpty() ? 'Cicilan paket' : 'Penjualan langsung'))
                    ->visibleFrom('lg'),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('xl'),
                TextColumn::make('patient_portion')->label('Tagihan pasien')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false))->sortable()->visibleFrom('md'),
                TextColumn::make('balance')
                    ->label('Sisa')
                    ->alignEnd()
                    ->state(fn (Invoice $record): int => $record->balance())
                    ->formatStateUsing(fn (int $state): string => Money::format($state, false))
                    ->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                TextColumn::make('status')->label('Status')->badge()->visibleFrom('md')
                    ->extraAttributes(['class' => 'klinik-status'])
                    ->description(fn (Invoice $record): ?string => $record->void_requested_at && $record->status !== InvoiceStatus::Void ? 'Menunggu persetujuan void' : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(InvoiceStatus::class)->multiple(),
                Filter::make('outstanding')->label('Belum lunas')->query(fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options()),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordUrl(fn (Invoice $record): string => $record->isDraft() && InvoiceResource::canEdit($record)
                ? InvoiceResource::getUrl('edit', ['record' => $record])
                : InvoiceResource::getUrl('view', ['record' => $record]))
            // Baris sudah bisa diklik; di HP tautan "Lihat" disembunyikan supaya kolom Sisa muat.
            ->recordActions([ViewAction::make()->extraAttributes(['class' => 'klinik-hide-sm'])]);
    }
}
