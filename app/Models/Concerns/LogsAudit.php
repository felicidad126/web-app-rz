<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail standar: semua perubahan atribut dicatat, kecuali kolom sensitif.
 *
 * Model bisa menambah kolom yang dikecualikan lewat properti `$auditExcept`.
 */
trait LogsAudit
{
    use LogsActivity;

    /** @var list<string> */
    private static array $alwaysExceptFromAudit = [
        'nik',
        'nik_hash',
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logAll()
            ->logExcept([...self::$alwaysExceptFromAudit, ...($this->auditExcept ?? [])])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
