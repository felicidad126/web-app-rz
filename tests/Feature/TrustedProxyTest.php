<?php

use App\Providers\AppServiceProvider;

it('memakai HTTPS dari header proxy hanya jika proxy dipercaya', function () {
    $headers = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'klinik.example.com', 'X-Forwarded-Port' => '443'];

    config(['trustedproxy.proxies' => null]);
    $this->get('/login', $headers)->assertDontSee('https://klinik.example.com/manifest.webmanifest', false);

    config(['trustedproxy.proxies' => '*']);
    $this->get('/login', $headers)->assertSee('https://klinik.example.com/manifest.webmanifest', false);
});

it('membuat link & redirect https bila APP_URL memakai https, tanpa header proxy', function () {
    config(['app.url' => 'https://klinik.example.com', 'trustedproxy.proxies' => null]);
    app()->getProvider(AppServiceProvider::class)->forceHttpsWhenConfigured();

    expect(url('/login'))->toStartWith('https://');
    expect($this->get('/')->headers->get('Location'))->toStartWith('https://');
});

it('tetap http bila APP_URL memakai http (akses via IP)', function () {
    config(['app.url' => 'http://192.168.1.10']);
    app()->getProvider(AppServiceProvider::class)->forceHttpsWhenConfigured();

    expect(url('/login'))->toStartWith('http://');
});
