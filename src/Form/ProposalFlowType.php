<?php

namespace App\Form;

use App\Cfp\MaterialTypeProvider;
use App\Cfp\ProposalDraft;
use App\Cfp\SpeakerData;
use App\Cfp\TalkData;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PolymorphicCollectionType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

final class ProposalFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep('speaker', SpeakerData::class, ['label' => 'About you'])
            ->addStep(
                $builder->createStepGroup('proposal')
                    ->addStep('talk', TalkData::class, ['label' => 'Your talk'])
                    ->addStep('materials', PolymorphicCollectionType::class, [
                        'label' => 'Material reviewers can look at',
                        'entry_types' => MaterialTypeProvider::TYPES,
                        'entry_type_provider' => new MaterialTypeProvider(),
                        'entry_options' => MaterialTypeProvider::LABELS,
                        'prototype_options' => MaterialTypeProvider::LABELS,
                        'allow_add' => true,
                        'allow_delete' => true,
                        'required' => false,
                    ])
            )
            ->addStep('review', CheckboxType::class, [
                'label' => 'I have read the code of conduct and I agree to follow it.',
                'mapped' => false,
                'constraints' => [new IsTrue()],
            ]);

        $builder->add('navigator', NavigatorFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProposalDraft::class,
            'step_property_path' => 'currentStep',
        ]);
    }
}
