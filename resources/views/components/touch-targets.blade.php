{{--
    Target sentuh untuk HP/tablet. Tautan aksi tabel Filament (Lihat/Ubah), tombol urut kolom,
    dan tombol hapus kecil hanya setinggi ±20px. Area ketuknya diperluas lewat ::after
    tanpa mengubah tata letak. Hanya berlaku untuk layar sentuh (pointer: coarse).
--}}
<style>
    @media (pointer: coarse) {
        .fi-ta-actions .fi-ac-link-action,
        .fi-ta-header-cell-sort-btn,
        .fi-badge-delete-btn,
        .fi-select-input-value-remove-btn {
            position: relative;
        }
        .fi-ta-actions .fi-ac-link-action::after,
        .fi-ta-header-cell-sort-btn::after {
            content: '';
            position: absolute;
            inset: -12px -4px;
        }
        .fi-badge-delete-btn::after,
        .fi-select-input-value-remove-btn::after {
            content: '';
            position: absolute;
            inset: -10px;
        }
    }
</style>
