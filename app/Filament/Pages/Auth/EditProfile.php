<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Profil pengguna dengan pola form yang sama seperti halaman lain: isian di dalam kartu "Akun",
 * dua kolom, label di atas isian (bukan label sebaris bawaan Filament untuk profil non-simple).
 */
class EditProfile extends BaseEditProfile
{
    #[Unexposed]
    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun')
                    ->columns(2)
                    ->components([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
