<?php

namespace App\Filament\Clusters\Stock\Resources\StockOpnames\Pages;

use App\Filament\Clusters\Stock\Resources\StockOpnames\StockOpnameResource;
use App\Filament\Clusters\Stock\StockActions;
use App\Models\Item;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\Stock\ExpiryDate;
use App\Services\Stock\StockOpnames;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Lembar hitung opname. Saldo sistem tidak ditampilkan selama menghitung (hitung "buta") agar tidak bias;
 * selisihnya terlihat oleh penyetuju setelah diajukan.
 */
class CountStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected static ?string $breadcrumb = 'Hitung';

    public function getTitle(): string|Htmlable
    {
        /** @var StockOpname $opname */
        $opname = $this->getRecord();

        return 'Hitung stok '.$opname->number;
    }

    public function getSubheading(): ?string
    {
        /** @var StockOpname $opname */
        $opname = $this->getRecord();
        $opname->loadMissing('branch');

        return $opname->branch->name.' · dimulai '.$opname->started_at->format('d/m/Y H:i')
            .($opname->rejected_reason ? ' · dikembalikan: '.$opname->rejected_reason : '')
            .' · isi jumlah fisik dalam satuan dasar; kosongkan baris barang yang memang tidak ada';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Lembar hitung')
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->relationship(modifyQueryUsing: fn ($query) => $query->with(['item.baseUnit', 'batch']))
                        ->table([
                            TableColumn::make('Barang')->width('30%'),
                            TableColumn::make('No. batch')->width('16%'),
                            TableColumn::make('ED')->width('12%'),
                            TableColumn::make('Harga pokok')->width('14%'),
                            TableColumn::make('Jumlah fisik')->width('13%'),
                            TableColumn::make('Catatan')->width('15%'),
                        ])
                        ->schema([
                            Hidden::make('item_batch_id'),
                            Select::make('item_id')
                                ->label('Barang')
                                ->options(fn (): array => Item::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required()
                                ->disabled(fn (Get $get): bool => filled($get('item_batch_id')))
                                ->dehydrated(),
                            TextInput::make('batch_number')->label('No. batch')->maxLength(60)->readOnly(fn (Get $get): bool => filled($get('item_batch_id'))),
                            TextInput::make('expired_at')
                                ->label('ED')
                                ->placeholder('08/2027')
                                ->formatStateUsing(fn ($state): ?string => $state ? Carbon::parse($state)->format('m/Y') : null)
                                ->dehydrateStateUsing(fn ($state) => ExpiryDate::parse($state)?->toDateString())
                                ->readOnly(fn (Get $get): bool => filled($get('item_batch_id'))),
                            TextInput::make('unit_cost')->label('Harga pokok')->numeric()->minValue(0)->prefix('Rp')->readOnly(fn (Get $get): bool => filled($get('item_batch_id'))),
                            TextInput::make('counted_qty')->label('Jumlah fisik')->numeric()->minValue(0)
                                ->suffix(fn (Get $get): ?string => Item::with('baseUnit')->find($get('item_id'))?->baseUnit->name),
                            TextInput::make('notes')->label('Catatan')->maxLength(255),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'system_qty' => 0])
                        ->addActionLabel('Tambah barang/batch yang ditemukan')
                        ->deletable(fn (): bool => false)
                        ->reorderable(false),
                ]),
            Textarea::make('notes')->label('Catatan opname')->rows(2),
        ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Hasil hitung disimpan';
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->label('Simpan hasil hitung')];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Ajukan untuk disetujui')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Hasil hitung dikunci dan dikirim ke admin klinik. Baris barang tanpa jumlah dianggap tidak ada perubahan.')
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    /** @var StockOpname $opname */
                    $opname = $this->getRecord();
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        StockOpnames::submit($opname, $user);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title('Belum bisa diajukan')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Opname diajukan')->success()->send();
                    $this->redirect(StockOpnameResource::getUrl('view', ['record' => $opname]));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        StockActions::branchId();

        return $data;
    }
}
