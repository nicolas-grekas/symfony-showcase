<?php

namespace App\Form;

use App\Admin\SlotData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SlotDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startsAt', DateTimeType::class, ['input' => 'date_point', 'widget' => 'single_text', 'required' => false])
            ->add('room', null, ['required' => false, 'placeholder' => 'Not scheduled']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SlotData::class,
        ]);
    }
}
