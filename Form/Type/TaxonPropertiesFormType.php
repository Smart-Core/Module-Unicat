<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Smart\CoreBundle\Form\TypeResolverTtait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonPropertiesFormType extends AbstractType
{
    use TypeResolverTtait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'properties'  => [],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach ($options['properties'] as $name => $options) {
            if ('image' === $options) {
                $type = AttributeImageFormType::class;
            } elseif (isset($options['type'])) {
                $type = $this->resolveTypeName($options['type']);
            }

            $builder->add($name, $type, [
                'required'  => false,
                'attr'      => isset($options['attr']) ? $options['attr'] : [],
            ]);
        }
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_properties';
    }
}
