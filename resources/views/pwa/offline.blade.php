{{-- Halaman offline PWA (di-cache service worker). Statis: jangan menampilkan data pengguna/pasien. --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#6c5dd3">
    <style>
        :root { color-scheme: light dark; --bg: #f6f6fb; --fg: #11142d; --muted: #55566a; --accent: #6c5dd3; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f172a; --fg: #f1f5f9; --muted: #94a3b8; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--fg); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 26rem; padding: 2rem 1.5rem; text-align: center; }
        img { width: 72px; height: 72px; }
        h1 { font-size: 1.25rem; margin: 1rem 0 .5rem; }
        p { color: var(--muted); margin: 0 0 1.5rem; }
        button { background: var(--accent); color: #fff; border: 0; border-radius: .5rem; padding: .75rem 1.25rem; font: inherit; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <img src="/icons/icon.svg" alt="">
    <h1>Tidak ada koneksi internet</h1>
    <p>{{ config('app.name') }} membutuhkan koneksi untuk menampilkan data. Periksa Wi-Fi atau data seluler, lalu coba lagi.</p>
    <button type="button" onclick="location.reload()">Coba lagi</button>
</main>
</body>
</html>
