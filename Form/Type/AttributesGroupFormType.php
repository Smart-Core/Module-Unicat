<?php

namespace SmartCore\Module\Unicat\Form\Type;

use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributesGroupFormType extends AbstractType
{
    /** @var UnicatConfiguration */
    protected $configuration;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->configuration = $options['unicat_configuration'];

        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('name')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => function (Options $options) {
                return $options['unicat_configuration']->getAttributesGroupClass();
            },
            'unicat_configuration' => null,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_attributes_group_'.$this->configuration->getName();
    }
}
