<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Smart\CoreBundle\Form\TypeResolverTtait;
use SmartCore\Bundle\SeoBundle\Form\Type\MetaFormType;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Form\Tree\TaxonTreeType;
use SmartCore\Module\Unicat\Model\AttributeModel;
use SmartCore\Module\Unicat\Model\TaxonModel;
use SmartCore\Module\Unicat\Service\UnicatService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemFormType extends AbstractType
{
    use TypeResolverTtait;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var UnicatConfiguration */
    protected $configuration;

    /** @var UnicatService  */
    protected $unicat;

    /**
     * @param ManagerRegistry $doctrine
     * @param UnicatService   $unicat
     */
    public function __construct(ManagerRegistry $doctrine, UnicatService $unicat)
    {
        $this->configuration = UnicatService::getCurrentConfigurationStatic();
        $this->doctrine      = $doctrine;
        $this->unicat        = $unicat;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('slug', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('is_enabled')
            ->add('position')
            ->add('meta', MetaFormType::class, ['label' => 'Meta tags'])
        ;

        foreach ($this->configuration->getTaxonomies() as $taxonomy) {
            $optionsCat = [
                'label'     => $taxonomy->getTitleForm(),
                'required'  => $taxonomy->getIsRequired(),
                'expanded'  => $taxonomy->isMultipleEntries(),
                'multiple'  => $taxonomy->isMultipleEntries(),
                'class'     => $this->configuration->getTaxonClass(),
            ];

            /** @var TaxonModel $taxon */
            foreach ($options['data']->getTaxonsSingle() as $taxon) {
                if ($taxon->getTaxonomy()->getName() === $taxonomy->getName()) {
                    if ($taxonomy->isMultipleEntries()) {
                        $optionsCat['data'][] = $taxon;
                    } else {
                        $optionsCat['data'] = $taxon;

                        break;
                    }
                }
            }

            $optionsCat['unicat_taxonomy'] = $taxonomy;
            $builder->add('taxonomy:'.$taxonomy->getName(), TaxonTreeType::class, $optionsCat);
        }

        /** @var $attribute AttributeModel */
        foreach ($this->unicat->getAttributes($this->configuration) as $attribute) {
            $type = $attribute->getType();
            $propertyOptions = [
                'required'  => $attribute->getIsRequired(),
                'label'     => $attribute->getTitle(),
            ];

            $attributeOptions = array_merge($propertyOptions, $attribute->getParam('form'));

            if ($attribute->isType('image')) {
                // @todo сделать виджет загрузки картинок.
                //$type = 'genemu_jqueryimage';
                $type = AttributeImageFormType::class;

                if (isset($options['data'])) {
                    $attributeOptions['data'] = $options['data']->getAttribute($attribute->getName());
                }
            }

            if ($attribute->isType('select')) {
                $type = ChoiceType::class;
            }

            if ($attribute->isType('multiselect')) {
                $type = ChoiceType::class;
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

            $type = $this->resolveTypeName($type);

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

    public function getBlockPrefix()
    {
        return 'unicat_item_'.$this->configuration->getName();
    }
}
