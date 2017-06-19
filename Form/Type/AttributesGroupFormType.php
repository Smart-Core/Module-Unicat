<?php

namespace SmartCore\Module\Unicat\Form\Type;

use SmartCore\Module\Unicat\Entity\UnicatAttributesGroup;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Service\UnicatService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributesGroupFormType extends AbstractType
{
    /** @var UnicatConfiguration */
    protected $configuration;

    public function __construct()
    {
        $this->configuration = UnicatService::getCurrentConfigurationStatic();
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('name')
            ->add('position')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatAttributesGroup::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_attributes_group';
    }
}
