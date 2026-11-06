<?php

// Istilah Inggris yang lazim (Dashboard, Password, Login, Reset, Upload, Download, Import, Export) — lihat docs/12.

return [
  'label' => 'Import :label',
  'modal' => [
    'heading' => 'Import :label',
    'form' => [
      'file' => [
        'placeholder' => 'Upload berkas CSV',
      ],
    ],
    'actions' => [
      'download_example' => [
        'label' => 'Download contoh berkas CSV',
      ],
      'import' => [
        'label' => 'Import',
      ],
    ],
  ],
  'notifications' => [
    'completed' => [
      'title' => 'Import selesai',
      'actions' => [
        'download_failed_rows_csv' => [
          'label' => 'Download informasi baris yang gagal diimport',
        ],
      ],
    ],
    'max_rows' => [
      'body' => 'Anda tidak dapat mengimport lebih dari :count baris sekaligus.',
    ],
    'started' => [
      'title' => 'Import dimulai',
      'body' => 'Mulai mengimport :count baris dan proses akan berjalan di belakang layar.',
    ],
  ],
  'failure_csv' => [
    'file_name' => 'import-:import_id-:csv_name-gagal',
  ],
];
