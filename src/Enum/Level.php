<?php

namespace App\Enum;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Level: string implements TranslatableInterface
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function getHelp(): TranslatableMessage
    {
        return new TranslatableMessage('level.'.$this->value.'.help');
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('level.'.$this->value, [], null, $locale);
    }
}
