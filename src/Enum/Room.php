<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Room: string implements TranslatableInterface
{
    case Main = 'main';
    case Second = 'second';
    case Workshop = 'workshop';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('room.'.$this->value, [], null, $locale);
    }
}
