<?php

namespace App\Form;

use App\Admin\Period;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $boundOptions = ['input' => 'date_point', 'widget' => 'single_text'];

        $builder
            ->add('from', $options['type'], $boundOptions)
            ->add('to', $options['type'], $boundOptions);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Period::class,
            'type' => DateType::class,
        ]);

        $resolver->setAllowedValues('type', [DateType::class, DateTimeType::class]);
    }
}
