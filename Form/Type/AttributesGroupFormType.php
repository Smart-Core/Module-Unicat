<?php

namespace SmartCore\Module\Unicat\Form\Type;

use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributesGroupFormType extends AbstractType
{
    /**
     * @var UnicatConfiguration
     */
    protected $configuration;

    /**
     * @param UnicatConfiguration $configuration
     */
    public function __construct(UnicatConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('name')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->configuration->getAttributesGroupClass(),
        ]);
    }

    public function getName()
    {
        return 'unicat_attributes_group_'.$this->configuration->getName();
    }
}
