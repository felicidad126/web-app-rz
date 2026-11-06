<?php

// Istilah Inggris yang lazim (Dashboard, Password, Login, Reset, Upload, Download, Import, Export) — lihat docs/12.

return [
  'form' => [
    'password' => [
      'label' => 'Password baru',
      'validation_attribute' => 'password',
    ],
    'password_confirmation' => [
      'label' => 'Konfirmasi password baru',
      'validation_attribute' => 'konfirmasi password',
    ],
    'current_password' => [
      'label' => 'Password saat ini',
      'below_content' => 'Untuk keamanan, harap konfirmasi password Anda untuk melanjutkan.',
      'validation_attribute' => 'password saat ini',
    ],
  ],
  'multi_factor_authentication' => [
    'label' => 'MFA (verifikasi dua langkah)',
  ],
];
