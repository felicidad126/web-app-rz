<?php

namespace App\Services\SatuSehat;

use App\Enums\SatuSehatIssue;
use RuntimeException;

/**
 * Kesalahan dari SATUSEHAT. Pesan sudah dibersihkan: tidak memuat URL (query bisa berisi NIK) atau token.
 */
class SatuSehatException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SatuSehatIssue $issue,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    /** Layak dicoba ulang otomatis (gangguan jaringan / server SATUSEHAT). */
    public function isTransient(): bool
    {
        return $this->issue === SatuSehatIssue::Unreachable;
    }
}
