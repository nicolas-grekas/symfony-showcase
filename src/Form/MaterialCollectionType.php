<?php

namespace App\Form;

use App\Cfp\SlidesData;
use App\Cfp\VideoData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lists slides and videos, with a prototype of each kind for adding entries in the browser.
 */
final class MaterialCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prototypes = [];
        foreach (['slides' => new SlidesData(), 'video' => new VideoData()] as $kind => $data) {
            $prototypes[$kind] = $builder->create($options['prototype_name'], MaterialDataType::class, ['data' => $data, 'required' => $options['required']])->getForm();
        }

        $builder->setAttribute('prototypes', $prototypes);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['prototypes'] = array_map(static fn (FormInterface $prototype) => $prototype->setParent($form)->createView($view), $form->getConfig()->getAttribute('prototypes'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => MaterialDataType::class,
            'allow_add' => true,
            'allow_delete' => true,
            // replaced by the prototypes of each kind
            'prototype' => false,
        ]);
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }
}
