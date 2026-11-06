@php
    /**
     * Odontogram interaktif (mockup v2 "Odontogram").
     *
     * @var array $chart     OdontogramVisual::data()
     * @var bool $editable   boleh mencatat (izin + rekam medis belum dikunci)
     * @var bool $withVisit  ada kunjungan → jenis "Dikerjakan" tersedia
         */
    use App\Services\Clinic\OdontogramVisual;
    $withVisit ??= false;
    $conditionGroups = collect($chart['conditions'])->groupBy('category');
    $layers = array_filter([
        'existing' => 'Kondisi saat ini',
        'planned' => 'Rencana',
        'done' => $withVisit ? 'Dikerjakan' : null,
    ]);
@endphp
<style>
    .odg { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1100px) { .odg { grid-template-columns: minmax(0, 1fr) 320px; } }
    .odg-card { background: #fff; border: 1px solid #e4e4e7; border-radius: 12px; }
    .odg-scroll { overflow-x: auto; padding: 14px 12px 6px; -webkit-overflow-scrolling: touch; }
    .odg-rows { display: grid; gap: 6px; min-width: 690px; }
    .odg-row { display: flex; justify-content: center; gap: 14px; }
    .odg-half { display: flex; gap: 4px; }
    .odg-row.mid { margin-bottom: 6px; }
    .odg-tooth { display: flex; flex-direction: column; align-items: center; gap: 1px; width: 38px; cursor: pointer; border-radius: 6px; padding: 2px 0; outline: none; }
    .odg-tooth:focus-visible, .odg-tooth.sel { background: #ecfdf3; box-shadow: 0 0 0 2px #0d6e63; }
    .odg-tooth svg { width: 34px; height: 34px; display: block; }
    .odg-tooth polygon { stroke: #71717a; stroke-width: 1; transition: fill .15s; }
    .odg-num { font-size: 10px; font-weight: 600; color: #3f3f46; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .odg-tooth.dec .odg-num { color: #a1a1aa; }
    .odg-code { font-size: 9px; font-family: var(--font-family-mono), ui-monospace, monospace; min-height: 11px; line-height: 1.2; }
    .odg-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; padding: 10px 16px; border-top: 1px solid #f4f4f5; font-size: 12px; color: #3f3f46; }
    .odg-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 6px; vertical-align: -1px; }
    .odg-panel { padding: 16px; display: grid; gap: 12px; }
    .odg-cross { display: grid; grid-template-columns: repeat(3, 48px); grid-template-rows: repeat(3, 48px); gap: 4px; }
    .odg-sf { border: 1px solid #d4d4d8; border-radius: 8px; background: #fff; font-weight: 600; font-size: 15px; color: #3f3f46; }
    .odg-sf.on { background: #0d6e63; border-color: #0d6e63; color: #fff; }
    .odg-sf:disabled { opacity: .35; cursor: not-allowed; }
    .odg-seg { display: flex; flex-wrap: wrap; gap: 6px; }
    .odg-seg button { padding: 8px 12px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; font-size: 13px; min-height: 40px; }
    .odg-seg button.on { background: #0d6e63; border-color: #0d6e63; color: #fff; }
    .odg-input { width: 100%; border: 1px solid #d4d4d8; border-radius: 8px; padding: 9px 10px; font-size: 14px; background: #fff; min-height: 42px; }
    .odg-save { width: 100%; background: #0d6e63; color: #fff; border-radius: 10px; padding: 12px; font-weight: 600; min-height: 46px; }
    .odg-save:disabled { opacity: .5; }
    .odg-info div { font-size: 13px; padding: 2px 0; }
    .odg-label { font-size: 12px; color: #52525b; margin-bottom: 4px; }
</style>
<div class="odg" wire:ignore
    x-data="{
        chart: @js($chart),
        editable: @js($editable),
        sel: null,
        picked: { t: false, r: false, b: false, l: false, c: false },
        code: '',
        layer: 'existing',
        notes: '',
        saving: false,
        message: '',
        tooth() { return this.sel ? this.chart.teeth[this.sel] : null },
        condition() { return this.chart.conditions.find(c => c.code === this.code) || null },
        wholeTooth() { const c = this.condition(); return c !== null && c.scope === 'tooth' },
        reset() { this.picked = { t: false, r: false, b: false, l: false, c: false } },
        select(n) { if (this.sel !== n) { this.sel = n; this.reset(); this.message = '' } },
        pick(n, k) {
            if (this.sel !== n) { this.select(n); return }
            if (this.editable && ! this.wholeTooth()) { this.picked[k] = ! this.picked[k] }
        },
        fill(n, k) { return (this.sel === n && this.picked[k]) ? '#fde68a' : this.chart.teeth[n].fill[k] },
        surfaces() { const t = this.tooth(); return t ? ['l', 'c', 'r', 't', 'b'].filter(k => this.picked[k]).map(k => t.surface[k]) : [] },
        canSave() { return this.sel && this.code && ! this.saving && (this.wholeTooth() || this.surfaces().length > 0) },
        async save() {
            if (! this.canSave()) return;
            this.saving = true;
            const result = await this.$wire.saveOdontogramFromChart(this.sel, this.surfaces(), this.code, this.layer, this.notes);
            this.saving = false;
            this.message = result.message;
            if (result.ok) { this.chart = result.chart; this.reset(); this.notes = '' }
        },
    }"
>
    <section class="odg-card">
        <div style="display: flex; justify-content: space-between; gap: 8px; padding: 12px 16px 0; flex-wrap: wrap">
            <div style="font-weight: 600">Odontogram</div>
            <div style="font-size: 12px; color: #52525b">Ketuk gigi untuk memilih & melihat riwayat{{ $editable ? ', ketuk lagi pada permukaan untuk menandai' : '' }}</div>
        </div>
        <div class="odg-scroll">
            <div class="odg-rows">
                @foreach ($chart['rows'] as $index => $row)
                    @php $half = intdiv(count($row), 2); $upper = $index <= 1; @endphp
                    <div class="odg-row {{ $index === 1 ? 'mid' : '' }}">
                        @foreach ([array_slice($row, 0, $half), array_slice($row, $half)] as $side)
                            <div class="odg-half">
                                @foreach ($side as $n)
                                    @php $t = $chart['teeth'][$n]; @endphp
                                    <div class="odg-tooth {{ $t['primary'] ? 'dec' : '' }}" :class="{ sel: sel === '{{ $n }}' }" role="button" tabindex="0"
                                        aria-label="Gigi {{ $n }}, {{ $t['name'] }}" @click="select('{{ $n }}')" @keydown.enter.prevent="select('{{ $n }}')">
                                        @if ($upper)<span class="odg-num">{{ $n }}</span>@endif
                                        <svg viewBox="-1 -1 42 42" aria-hidden="true">
                                            @foreach (['t', 'r', 'b', 'l', 'c'] as $k)
                                                <polygon points="{{ $t['poly'][$k] }}" :fill="fill('{{ $n }}', '{{ $k }}')" @click.stop="pick('{{ $n }}', '{{ $k }}')"></polygon>
                                            @endforeach
                                            <g x-show="chart.teeth['{{ $n }}'].mark === 'mis'" style="pointer-events: none">
                                                <line x1="2" y1="2" x2="38" y2="38" stroke="#18181b" stroke-width="3"></line>
                                                <line x1="38" y1="2" x2="2" y2="38" stroke="#18181b" stroke-width="3"></line>
                                            </g>
                                            <polyline x-show="chart.teeth['{{ $n }}'].mark === 'rrx'" points="6,6 20,34 34,6" fill="none" stroke="#18181b" stroke-width="3" style="pointer-events: none"></polyline>
                                            <rect x-show="chart.teeth['{{ $n }}'].ring" x="1.5" y="1.5" width="37" height="37" fill="none" :stroke="chart.teeth['{{ $n }}'].ring" stroke-width="3.5" style="pointer-events: none"></rect>
                                            <rect x-show="chart.teeth['{{ $n }}'].plan" x="-0.5" y="-0.5" width="41" height="41" fill="none" stroke="{{ OdontogramVisual::PLAN_STROKE }}" stroke-width="2" stroke-dasharray="4 2" style="pointer-events: none"></rect>
                                        </svg>
                                        @unless ($upper)<span class="odg-num">{{ $n }}</span>@endunless
                                        <span class="odg-code" :style="'color:' + chart.teeth['{{ $n }}'].labelColor" x-text="chart.teeth['{{ $n }}'].label"></span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="odg-legend">
            <template x-for="item in chart.legend" :key="item.label">
                <span><i :style="'background:' + item.color"></i><span x-text="item.label + (item.code ? ' ' + item.code : '')"></span></span>
            </template>
            <span>✕ hilang (mis)</span><span>V sisa akar (rrx)</span><span>Bingkai: mahkota/jembatan/implan</span>
        </div>
        <div style="padding: 10px 16px; border-top: 1px solid #f4f4f5; font-size: 13px; display: flex; flex-wrap: wrap; gap: 4px 20px">
            <span><strong>DMF-T</strong> (otomatis): D <span x-text="chart.indices.D"></span> · M <span x-text="chart.indices.M"></span> · F <span x-text="chart.indices.F"></span> = <strong x-text="chart.indices.total"></strong></span>
            <span x-show="chart.indices.total_primary > 0"><strong>def-t</strong>: d <span x-text="chart.indices.d"></span> · e <span x-text="chart.indices.e"></span> · f <span x-text="chart.indices.f"></span> = <strong x-text="chart.indices.total_primary"></strong></span>
        </div>
    </section>

    <section class="odg-card">
        <div x-show="! sel" class="odg-panel" style="color: #52525b; font-size: 14px">
            Pilih gigi pada odontogram.
            <div style="font-size: 12px">M mesial · D distal · O oklusal/insisal · V bukal/labial · L lingual/palatal</div>
        </div>
        <div x-show="sel" x-cloak class="odg-panel">
            <div>
                <div style="font-weight: 600; font-size: 16px" x-text="sel ? 'Gigi ' + sel : ''"></div>
                <div style="font-size: 13px; color: #52525b" x-text="tooth() ? tooth().name : ''"></div>
            </div>
            <div class="odg-info">
                <template x-for="(line, i) in (tooth() ? tooth().info : [])" :key="i">
                    <div :style="line.tone === 'plan' ? 'color:#b42318' : (line.tone === 'here' ? 'color:#175cd3' : (line.tone === 'muted' ? 'color:#71717a' : ''))" x-text="line.text"></div>
                </template>
            </div>
            @if ($editable)
                <div>
                    <div class="odg-label">Jenis catatan</div>
                    <div class="odg-seg">
                        @foreach ($layers as $value => $label)
                            <button type="button" :class="{ on: layer === '{{ $value }}' }" @click="layer = '{{ $value }}'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <div class="odg-label">Kondisi / kode</div>
                    <select class="odg-input" x-model="code" @change="if (wholeTooth()) reset()">
                        <option value="">Pilih kode…</option>
                        @foreach ($conditionGroups as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach ($items as $c)
                                    <option value="{{ $c['code'] }}">{{ $c['code'] }} · {{ $c['name'] }}{{ $c['scope'] === 'tooth' ? ' (seluruh gigi)' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div style="display: flex; gap: 14px; align-items: center">
                    <div class="odg-cross">
                        @foreach ([['', null], ['t', 't'], ['', null], ['l', 'l'], ['c', 'c'], ['r', 'r'], ['', null], ['b', 'b'], ['', null]] as [$k, $key])
                            @if ($key)
                                <button type="button" class="odg-sf" :class="{ on: picked.{{ $key }} }" :disabled="wholeTooth()"
                                    @click="picked.{{ $key }} = ! picked.{{ $key }}" x-text="tooth() ? tooth().surface.{{ $key }} : ''" :aria-pressed="picked.{{ $key }}"></button>
                            @else
                                <span></span>
                            @endif
                        @endforeach
                    </div>
                    <div style="font-size: 12px; color: #52525b" x-text="wholeTooth() ? 'Kode ini untuk seluruh gigi; permukaan tidak dipakai.' : 'Ketuk permukaan. Letaknya sama dengan gambar gigi di kiri.'"></div>
                </div>
                <input type="text" class="odg-input" x-model="notes" maxlength="255" placeholder="Catatan (opsional)">
                <button type="button" class="odg-save" :disabled="! canSave()" @click="save()" x-text="saving ? 'Menyimpan…' : 'Simpan catatan'"></button>
                <div x-show="message" x-text="message" style="font-size: 13px; color: #3f3f46"></div>
            @else
                <div style="font-size: 12px; color: #71717a">Hanya baca{{ $withVisit ? ' (rekam medis sudah ditandatangani atau tidak punya izin ubah)' : '' }}.</div>
            @endif
        </div>
    </section>
</div>
