<?php

it('menyediakan manifest PWA yang valid', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue($icon['src']);
    }
});

it('memasang manifest dan service worker di halaman panel', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('serviceWorker.register', false);
});

it('service worker tidak meng-cache halaman berisi data', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("request.mode === 'navigate'")
        ->and($sw)->toContain('/offline.html')
        // Hanya aset statis yang masuk cache.put()
        ->and(substr_count($sw, 'cache.put('))->toBe(1);
});
