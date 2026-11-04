<?php

use App\Support\BlindIndex;

beforeEach(fn () => config(['clinic.blind_index_key' => base64_encode(str_repeat('k', 32))]));

it('menormalisasi NIK menjadi digit saja', function () {
    expect(BlindIndex::normalize('3171 0123-4567.8901'))->toBe('3171012345678901')
        ->and(BlindIndex::normalize('  '))->toBeNull()
        ->and(BlindIndex::normalize(null))->toBeNull();
});

it('menghasilkan hash deterministik yang tidak sama dengan NIK', function () {
    $a = BlindIndex::hash('3171012345678901');

    expect($a)->toBe(BlindIndex::hash('3171-0123-4567-8901'))
        ->and($a)->toHaveLength(64)
        ->and($a)->not->toContain('3171012345678901');
});

it('hash berbeda jika kunci berbeda', function () {
    $a = BlindIndex::hash('3171012345678901');
    config(['clinic.blind_index_key' => base64_encode(str_repeat('x', 32))]);

    expect(BlindIndex::hash('3171012345678901'))->not->toBe($a);
});

it('gagal jika kunci belum diisi', function () {
    config(['clinic.blind_index_key' => null]);

    BlindIndex::hash('3171012345678901');
})->throws(RuntimeException::class);
