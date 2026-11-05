<?php

namespace App\Exceptions;

use RuntimeException;

/** Rekam medis yang sudah ditandatangani tidak boleh diubah; koreksi lewat addendum. */
class MedicalRecordLockedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Rekam medis sudah ditandatangani dan dikunci. Koreksi hanya lewat addendum.');
    }
}
