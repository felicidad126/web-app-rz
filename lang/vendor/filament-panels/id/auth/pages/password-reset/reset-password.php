<?php

// Istilah Inggris yang lazim (Dashboard, Password, Login, Reset, Upload, Download, Import, Export) — lihat docs/12.

return [
  'title' => 'Reset password',
  'heading' => 'Reset password',
  'form' => [
    'password' => [
      'label' => 'Password baru',
      'validation_attribute' => 'password',
    ],
    'password_confirmation' => [
      'label' => 'Konfirmasi password baru',
    ],
    'actions' => [
      'reset' => [
        'label' => 'Reset password',
      ],
    ],
  ],
];
