<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar inisial berbentuk SVG data URI, dibuat lokal.
 *
 * Pengganti UiAvatarsProvider bawaan Filament yang mengirim nama pengguna ke ui-avatars.com.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = collect(preg_split('/\s+/', trim(preg_replace('/^(drg?\.?|dr\.?)\s+/i', '', $name))))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#0d6e63"/><text x="50%%" y="50%%" dy=".35em" text-anchor="middle" font-family="system-ui,sans-serif" font-size="26" font-weight="600" fill="#ffffff">%s</text></svg>',
            e($initials ?: '?'),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
