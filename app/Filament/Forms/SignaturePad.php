<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;

/**
 * Kotak tanda tangan (canvas) untuk tablet/HP. State berupa data URL PNG.
 */
class SignaturePad extends Field
{
    protected string $view = 'filament.forms.signature-pad';
}
