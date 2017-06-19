<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Doctrine\ORM\EntityRepository;
use SmartCore\Module\Unicat\Entity\UnicatAttributesGroup;
use SmartCore\Module\Unicat\Entity\UnicatItemType;
use SmartCore\Module\Unicat\Service\UnicatService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title',      null, ['attr'  => ['autofocus' => 'autofocus']])
            ->add('name')
            ->add('position')
            ->add('to_string_pattern')
            ->add('attributes_groups', EntityType::class, [
                'expanded' => true,
                'multiple' => true,
                'class'         => UnicatAttributesGroup::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.configuration = :configuration')
                        ->setParameter('configuration', UnicatService::getCurrentConfigurationStatic());
                },
                'required' => false,
            ])
            ->add('taxonomies', null, [
                'expanded' => true,
            ])
            //->add('order_by_attr')
            ->add('order_by_direction')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatItemType::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_item_type';
    }
}
