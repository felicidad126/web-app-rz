<?php

// Istilah Inggris yang lazim (Dashboard, Password, Login, Reset, Upload, Download, Import, Export) — lihat docs/12.

return [
  'title' => 'Login',
  'heading' => 'Login ke akun Anda',
  'actions' => [
    'request_password_reset' => [
      'label' => 'Lupa password?',
    ],
  ],
  'form' => [
    'password' => [
      'label' => 'Password',
    ],
    'actions' => [
      'authenticate' => [
        'label' => 'Login',
      ],
    ],
  ],
];
