<?php

it('memakai HTTPS dari header proxy hanya jika proxy dipercaya', function () {
    $headers = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'klinik.example.com', 'X-Forwarded-Port' => '443'];

    config(['trustedproxy.proxies' => null]);
    $this->get('/login', $headers)->assertDontSee('https://klinik.example.com/manifest.webmanifest', false);

    config(['trustedproxy.proxies' => '*']);
    $this->get('/login', $headers)->assertSee('https://klinik.example.com/manifest.webmanifest', false);
});
