<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title',      null, ['attr'  => ['autofocus' => 'autofocus']])
            ->add('name')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'SmartCore\Module\Unicat\Entity\UnicatConfiguration',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_configuration';
    }
}
