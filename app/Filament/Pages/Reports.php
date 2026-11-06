<?php

namespace App\Filament\Pages;

use App\Filament\Clinic\PractitionerOptions;
use App\Filament\Support\NavGroup;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Reports\ReportRequest;
use App\Support\BranchContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Laporan (Planning §5.8) dengan ekspor Excel/PDF. Isi & batasan izin sama dengan file yang diunduh.
 *
 * @property-read Schema $form
 */
class Reports extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::REPORTS;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static ?string $slug = 'laporan';

    /** @var array<string, mixed> */
    public array $filters = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('View:Reports') && ReportRegistry::options($user) !== [];
    }

    public function mount(): void
    {
        $this->form->fill([
            'report' => array_key_first(ReportRegistry::options($this->user())),
            'from' => now()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'branch' => null,
            'practitioner' => null,
            'group' => 'branch',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $class = fn (Get $get): ?string => ReportRegistry::find((string) $get('report'));

        return $schema->statePath('filters')->components([
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 6])->schema([
                Select::make('report')->label('Laporan')->options(fn (): array => ReportRegistry::options($this->user()))->required()->live()->columnSpan(['lg' => 2]),
                DatePicker::make('from')->label('Dari')->native(false)->displayFormat('d/m/Y')->live()
                    ->visible(fn (Get $get): bool => ($c = $class($get)) === null || $c::usesPeriod()),
                DatePicker::make('to')->label('Sampai')->native(false)->displayFormat('d/m/Y')->live()
                    ->visible(fn (Get $get): bool => ($c = $class($get)) === null || $c::usesPeriod()),
                Select::make('branch')->label('Cabang')->options(fn (): array => BranchContext::options())->placeholder('Semua cabang')->live()
                    ->visible(fn (): bool => count(BranchContext::options()) > 1),
                Select::make('practitioner')->label('Dokter')->options(fn (): array => PractitionerOptions::all())->placeholder('Semua')->live()
                    ->visible(fn (Get $get): bool => $class($get) !== null && $class($get)::supportsPractitioner() && ! $class($get)::ownOnly($this->user()) && $get('group') !== 'method'),
                Select::make('group')->label('Kelompokkan')->options(fn (Get $get): array => ($c = $class($get)) !== null ? $c::groupOptions() : [])->live()
                    ->visible(fn (Get $get): bool => ($c = $class($get)) !== null && $c::groupOptions() !== []),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Unduh Excel')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn (): string => route('reports.export', [...$this->filters, 'format' => 'xlsx'])),
            Action::make('pdf')->label('Unduh PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->url(fn (): string => route('reports.export', [...$this->filters, 'format' => 'pdf']), shouldOpenInNewTab: true),
        ];
    }

    public function report(): ?Report
    {
        return ReportRequest::make($this->user(), $this->filters);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
