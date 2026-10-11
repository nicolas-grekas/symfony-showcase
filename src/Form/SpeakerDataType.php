<?php

namespace App\Form;

use App\Cfp\SpeakerData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SpeakerDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('company', null, ['required' => false])
            ->add('phone', TelType::class, ['required' => false, 'help' => 'Only given to the travel agency if your talk is accepted.'])
            ->add('bio', TextareaType::class, ['help' => 'A few words about you, as they will appear on the website.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SpeakerData::class,
        ]);
    }
}
