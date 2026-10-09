<?php

namespace App\Cfp;

use Symfony\Component\Form\EntryTypeProviderInterface;

final class MaterialTypeProvider implements EntryTypeProviderInterface
{
    public const TYPES = [
        'slides' => SlidesData::class,
        'video' => VideoData::class,
    ];

    public const LABELS = [
        'slides' => ['label' => 'Slides'],
        'video' => ['label' => 'Video of a previous delivery'],
    ];

    public function forModelData(mixed $data): string
    {
        return $data instanceof VideoData ? 'video' : 'slides';
    }

    public function forSubmittedData(mixed $data): string
    {
        return \is_array($data) && \array_key_exists('minutes', $data) ? 'video' : 'slides';
    }
}
