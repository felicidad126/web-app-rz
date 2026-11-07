<?php

it('menyediakan manifest PWA yang valid dengan nama dari APP_NAME', function () {
    config(['app.name' => 'Klinik Contoh Sejahtera', 'app.short_name' => 'Klinik Contoh']);

    $response = $this->get('/manifest.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->assertCookieMissing('dental-pms-session');
    $manifest = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Klinik Contoh Sejahtera')
        ->and($manifest['short_name'])->toBe('Klinik Contoh')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue($icon['src']);
    }
});

it('halaman offline statis memakai nama aplikasi, tanpa sesi', function () {
    config(['app.name' => 'Klinik Contoh Sejahtera']);

    $this->get('/offline.html')->assertOk()->assertSee('Klinik Contoh Sejahtera membutuhkan koneksi')->assertCookieMissing('dental-pms-session');
});

it('nama aplikasi tampil di panel; cookie sesi tidak berubah saat nama diganti', function () {
    expect(config('session.cookie'))->toBe('dental-pms-session')
        ->and(config('cache.prefix'))->toBe('dental-pms-cache-')
        ->and(config('database.redis.options.prefix'))->toBe('dental-pms-database-');

    $this->get('/login')->assertOk()->assertSee(config('app.name'))->assertSee('content="'.config('app.short_name').'"', false)
        // Logo klinik di header/login dan favicon dari logo yang sama.
        ->assertSee('icons/logo.svg', false)->assertSee('favicon.png?v=', false);

    foreach (['icons/logo.svg', 'icons/icon.svg', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png', 'icons/apple-touch-icon.png', 'favicon.ico', 'favicon.png'] as $file) {
        expect(filesize(public_path($file)))->toBeGreaterThan(0, $file);
    }
});

it('memasang manifest dan service worker di halaman panel', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('serviceWorker.register', false)
        // Area ketuk aksi tabel diperbesar khusus layar sentuh.
        ->assertSee('@media (pointer: coarse)', false);
});

it('service worker tidak meng-cache halaman berisi data', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("request.mode === 'navigate'")
        ->and($sw)->toContain('/offline.html')
        // Hanya aset statis yang masuk cache.put()
        ->and(substr_count($sw, 'cache.put('))->toBe(1);
});
