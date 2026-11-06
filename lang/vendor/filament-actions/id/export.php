<?php

// Istilah Inggris yang lazim (Dashboard, Password, Login, Reset, Upload, Download, Import, Export) — lihat docs/12.

return [
  'label' => 'Export :label',
  'modal' => [
    'heading' => 'Export :label',
    'actions' => [
      'export' => [
        'label' => 'Export',
      ],
    ],
  ],
  'notifications' => [
    'completed' => [
      'title' => 'Export selesai',
      'actions' => [
        'download_csv' => [
          'label' => 'Download .csv',
        ],
        'download_xlsx' => [
          'label' => 'Download .xlsx',
        ],
      ],
    ],
    'max_rows' => [
      'title' => 'Export terlalu besar',
      'body' => 'Anda tidak dapat mengexport lebih dari :count sekaligus.',
    ],
    'no_columns' => [
      'body' => 'Pilih setidaknya satu kolom untuk diexport.',
    ],
    'started' => [
      'title' => 'Export dimulai',
      'body' => 'Mulai mengexport :count baris dan proses akan berjalan di belakang layar.',
    ],
  ],
  'file_name' => 'export-:export_id-:model',
];
