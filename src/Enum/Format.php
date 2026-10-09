<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Format: string implements TranslatableInterface
{
    case Talk = 'talk';
    case Workshop = 'workshop';
    case Lightning = 'lightning';

    public function minutes(): int
    {
        return match ($this) {
            self::Talk => 40,
            self::Workshop => 180,
            self::Lightning => 10,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('format.'.$this->value, ['%minutes%' => $this->minutes()], null, $locale);
    }
}
