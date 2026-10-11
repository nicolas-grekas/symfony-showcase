<?php

namespace App\Form;

use App\Cfp\MaterialData;
use App\Cfp\SlidesData;
use App\Cfp\VideoData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edits slides or a video: the data tells which one, or for a new entry, whether a length is submitted.
 */
final class MaterialDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, ['default_protocol' => 'https'])
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
                if ($event->getData() instanceof VideoData) {
                    self::addMinutes($event->getForm());
                }
            })
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                $data = $event->getData();

                if (null === $event->getForm()->getData() && \is_array($data) && \array_key_exists('minutes', $data)) {
                    self::addMinutes($event->getForm());
                }
            });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['label'] ??= $form->has('minutes') ? 'Video of a previous delivery' : 'Slides';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaterialData::class,
            'empty_data' => static fn (FormInterface $form) => $form->has('minutes') ? new VideoData() : new SlidesData(),
        ]);
    }

    private static function addMinutes(FormInterface $form): void
    {
        $form->add('minutes', IntegerType::class, ['label' => 'Length in minutes', 'required' => false]);
    }
}
