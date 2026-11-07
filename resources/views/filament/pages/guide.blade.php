@php
    // Ikon kartu peran (garis 1,6 px, warna teal lewat CSS). Gigi untuk dokter tidak ada di Heroicons.
    $icons = [
        'umum' => '<path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/>',
        'resepsionis' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
        'dokter' => '<path d="M12 4c-2-1.2-6-1.5-6 3 0 3 1.5 6 2 9 .3 2 1 3.5 2 3.5s1.3-2.5 2-4c.7 1.5 1 4 2 4s1.7-1.5 2-3.5c.5-3 2-6 2-9 0-4.5-4-4.2-6-3z"/>',
        'perawat' => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z"/><path d="M4 8l8 4 8-4M12 12v8"/>',
        'kasir' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'admin' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14.2 3h-4l-.4 2.5a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2 1.2l.4 2.5h4l.4-2.5a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/>',
        'owner' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
@endphp
<x-filament-panels::page>
    @if ($role === null)
        {{-- Halaman utama: judul + cari, kartu peran, pertanyaan umum (disepakati dari mockup 7 Nov 2026). --}}
        <div class="klinik-guide">
            <section class="klinik-guide-hero"
                x-data="{
                    q: '',
                    items: @js($search),
                    get words() { return this.q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').split(/\s+/).filter(Boolean) },
                    get results() { const w = this.words; return w.length ? this.items.filter(i => w.every(x => i.haystack.includes(x))).slice(0, 8) : [] },
                    go() { if (this.results.length) window.location = this.results[0].url },
                }">
                <h1>Butuh bantuan?</h1>
                <p>Panduan pemakaian {{ config('app.name') }}, dikelompokkan per peran.<br>Cari topik atau pilih peran Anda.</p>
                <form class="klinik-guide-search" x-on:submit.prevent="go()" role="search">
                    <div class="klinik-guide-search-box">
                        <input type="search" x-model="q" placeholder="Cari panduan, mis. tutup kasir, reset MFA, minta beli…" aria-label="Cari panduan" autocomplete="off">
                        <div class="klinik-guide-results" x-show="q.trim() !== ''" x-cloak>
                            <template x-for="r in results" :key="r.url">
                                <a :href="r.url" class="klinik-guide-result">
                                    <span class="klinik-guide-result-section" x-text="r.section"></span>
                                    <span class="klinik-guide-result-title" x-text="r.title"></span>
                                    <span class="klinik-guide-result-snippet" x-text="r.snippet"></span>
                                </a>
                            </template>
                            <p class="klinik-guide-empty" x-show="results.length === 0">Tidak ada topik yang cocok. Coba kata lain, atau tanyakan ke Admin klinik.</p>
                        </div>
                    </div>
                    <x-filament::button type="submit" size="lg">Cari</x-filament::button>
                </form>
            </section>

            <section class="klinik-guide-cards" aria-label="Panduan per peran">
                @foreach ($cards as $key => $card)
                    <a href="{{ $card['url'] }}" @class(['klinik-guide-card', 'is-mine' => $card['mine']])>
                        @if ($card['mine'])<span class="klinik-guide-mine">Peran Anda</span>@endif
                        <span class="klinik-guide-card-icon"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$key] ?? $icons['umum'] !!}</svg></span>
                        <span class="klinik-guide-card-title">{{ $card['title'] }}</span>
                        <span class="klinik-guide-card-text">{{ $card['description'] }}</span>
                        <span class="klinik-guide-card-link">Lihat panduan</span>
                    </a>
                @endforeach
            </section>

            @if ($faq !== [])
                <section class="klinik-guide-faq" id="faq"
                    x-data="{ open: 0, fromHash() { if (location.hash.startsWith('#faq-')) this.open = parseInt(location.hash.slice(5), 10) } }"
                    x-init="fromHash()" x-on:hashchange.window="fromHash()">
                    <div>
                        <h2>Pertanyaan umum</h2>
                        <p>Hal yang paling sering ditanyakan staf. Tidak menemukan jawabannya? Hubungi Admin klinik.</p>
                    </div>
                    <div>
                        @foreach ($faq as $i => $item)
                            <div class="klinik-guide-q" id="faq-{{ $i }}" :class="{ 'is-open': open === {{ $i }} }">
                                <button type="button" class="klinik-guide-q-title" x-on:click="open = open === {{ $i }} ? null : {{ $i }}" :aria-expanded="open === {{ $i }}">
                                    <span>{{ $item['title'] }}</span>
                                    <span class="klinik-guide-q-icon" aria-hidden="true" x-text="open === {{ $i }} ? '−' : '+'">+</span>
                                </button>
                                <div class="klinik-guide-prose klinik-guide-q-answer" x-show="open === {{ $i }}" x-collapse>{!! $item['html'] !!}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @else
        {{-- Halaman satu peran: daftar topik di kiri, isi di kanan. --}}
        <div class="klinik-guide-role">
            <nav class="klinik-guide-toc" aria-label="Topik">
                <a href="{{ $indexUrl }}" class="klinik-guide-back">← Semua panduan</a>
                <div class="klinik-guide-toc-label">Topik</div>
                @foreach ($topics as $topic)
                    <a href="#{{ $topic['slug'] }}">{{ $topic['title'] }}</a>
                @endforeach
            </nav>
            <article class="klinik-guide-article">
                @foreach ($topics as $topic)
                    <section id="{{ $topic['slug'] }}" class="klinik-guide-topic">
                        <h2>{{ $topic['title'] }}</h2>
                        <div class="klinik-guide-prose">{!! $topic['html'] !!}</div>
                    </section>
                @endforeach
            </article>
        </div>
    @endif
</x-filament-panels::page>
