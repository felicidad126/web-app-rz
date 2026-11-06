<?php

namespace App\Filament\Resources\CashSessions;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\CashSessions\Pages\CloseCashSession;
use App\Filament\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Resources\CashSessions\Pages\ViewCashSession;
use App\Filament\Support\NavGroup;
use App\Models\CashSession;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CashSessionResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = CashSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::BILLING;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'sesi kasir';

    protected static ?string $pluralModelLabel = 'sesi kasir';

    protected static ?string $navigationLabel = 'Tutup kasir';

    protected static ?string $slug = 'sesi-kasir';

    /** Kasir hanya melihat sesinya sendiri; owner/admin melihat semua sesi di cabang yang boleh diakses. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereIn('branch_id', BranchContext::available()->pluck('id'));
        $user = auth()->user();

        if ($user instanceof User && ! self::seesAllSessions()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function seesAllSessions(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasAnyRole(['owner', 'admin', config('filament-shield.super_admin.name')]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'branch']))
            ->columns([
                TextColumn::make('opened_at')->label('Dibuka')->dateTime('d/m/Y H:i')
                    ->description(fn (CashSession $record): string => $record->closed_at ? 'ditutup '.$record->closed_at->format('H:i') : 'masih terbuka'),
                // Kasir hanya melihat sesinya sendiri, jadi kolom kasir hanya untuk owner/admin.
                TextColumn::make('user.name')->label('Kasir')->visible(fn (): bool => self::seesAllSessions())->visibleFrom('sm'),
                TextColumn::make('branch.name')->label('Cabang')->visibleFrom('lg'),
                TextColumn::make('opening_cash')->label('Kas awal')->alignEnd()->formatStateUsing(fn (int $state): string => Money::format($state, false))->visibleFrom('xl'),
                TextColumn::make('expected_cash')->label('Tunai sistem')->alignEnd()->formatStateUsing(fn (?int $state): string => Money::format($state, false))->placeholder('—')->visibleFrom('md'),
                TextColumn::make('counted_cash')->label('Tunai dihitung')->alignEnd()->formatStateUsing(fn (?int $state): string => Money::format($state, false))->placeholder('—')->visibleFrom('md'),
                TextColumn::make('difference')->label('Selisih')->alignEnd()
                    ->formatStateUsing(fn (?int $state): string => Money::format($state, false))
                    ->color(fn (?int $state): ?string => $state ? 'danger' : null)
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('open')->label('Status')->placeholder('Semua')->trueLabel('Terbuka')->falseLabel('Ditutup')
                    ->queries(true: fn (Builder $q) => $q->whereNull('closed_at'), false: fn (Builder $q) => $q->whereNotNull('closed_at')),
                SelectFilter::make('branch_id')->label('Cabang')->options(fn (): array => BranchContext::options()),
            ])
            ->recordActions([
                Action::make('close')
                    ->label('Tutup kasir')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->button()
                    ->color('gray')
                    ->visible(fn (CashSession $record): bool => $record->isOpen() && $record->user_id === auth()->id())
                    ->url(fn (CashSession $record): string => self::getUrl('close', ['record' => $record])),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashSessions::route('/'),
            'view' => ViewCashSession::route('/{record}'),
            'close' => CloseCashSession::route('/{record}/tutup'),
        ];
    }
}
