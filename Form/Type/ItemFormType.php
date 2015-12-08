<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use SmartCore\Bundle\CMSBundle\Container;
use SmartCore\Bundle\SeoBundle\Form\Type\MetaFormType;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Form\Tree\TaxonTreeType;
use SmartCore\Module\Unicat\Model\AttributeModel;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemFormType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @var UnicatConfiguration
     */
    protected $configuration;

    /**
     * @param UnicatConfiguration $configuration
     */
    public function __construct(UnicatConfiguration $configuration, ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        $this->configuration = $configuration;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('slug', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('is_enabled')
            ->add('position')
            ->add('meta', new MetaFormType(), ['label' => 'Meta tags'])
        ;

        foreach ($this->configuration->getStructures() as $structure) {
            $optionsCat = [
                'label'     => $structure->getTitleForm(),
                'required'  => $structure->getIsRequired(),
                'expanded'  => $structure->isMultipleEntries(),
                'multiple'  => $structure->isMultipleEntries(),
                'class'     => $this->configuration->getTaxonClass(),
            ];

            /** @var TaxonModel $taxon */
            foreach ($options['data']->getTaxons() as $taxon) {
                if ($taxon->getStructure()->getName() === $structure->getName()) {
                    if ($structure->isMultipleEntries()) {
                        $optionsCat['data'][] = $taxon;
                    } else {
                        $optionsCat['data'] = $taxon;

                        break;
                    }
                }
            }

            $taxonTreeType = (new TaxonTreeType($this->doctrine))->setStructure($structure);
            $builder->add('structure:'.$structure->getName(), $taxonTreeType, $optionsCat);
        }

        /** @var $attribute AttributeModel */
        foreach (Container::getContainer()->get('unicat')->getAttributes($this->configuration) as $attribute) {
            $type = $attribute->getType();
            $propertyOptions = [
                'required'  => $attribute->getIsRequired(),
                'label'     => $attribute->getTitle(),
            ];

            $attributeOptions = array_merge($propertyOptions, $attribute->getParam('form'));

            if ($attribute->isType('image')) {
                // @todo сделать виджет загрузки картинок.
                //$type = 'genemu_jqueryimage';
                $type = new AttributeImageFormType();

                if (isset($options['data'])) {
                    $attributeOptions['data'] = $options['data']->getAttribute($attribute->getName());
                }
            }

            if ($attribute->isType('select')) {
                $type = 'choice';
            }

            if ($attribute->isType('multiselect')) {
                $type = 'choice';
                $attributeOptions['expanded'] = true;
                //$propertyOptions['multiple'] = true; // @todo FS#407 продумать мультиселект
            }

            if (isset($attributeOptions['constraints'])) {
                $constraintsObjects = [];

                foreach ($attributeOptions['constraints'] as $constraintsList) {
                    foreach ($constraintsList as $constraintClass => $constraintParams) {
                        $_class = '\Symfony\Component\Validator\Constraints\\'.$constraintClass;

                        $constraintsObjects[] = new $_class($constraintParams);
                    }
                }

                $attributeOptions['constraints'] = $constraintsObjects;
            }

            $builder->add('attribute:'.$attribute->getName(), $type, $attributeOptions);
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->configuration->getItemClass(),
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'unicat_item_'.$this->configuration->getName();
    }
}
