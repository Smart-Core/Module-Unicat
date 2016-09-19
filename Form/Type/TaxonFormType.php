<?php

namespace SmartCore\Module\Unicat\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use SmartCore\Bundle\SeoBundle\Form\Type\MetaFormType;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Entity\UnicatStructure;
use SmartCore\Module\Unicat\Form\Tree\TaxonTreeType;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class TaxonFormType extends AbstractType
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
     * @param ManagerRegistry $doctrine
     */
    public function __construct(UnicatConfiguration $configuration, ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        $this->configuration = $configuration;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var TaxonModel $taxon */
        $taxon = $options['data'];

        $taxonTreeType = (new TaxonTreeType($this->doctrine))->setStructure($taxon->getStructure());

        $builder
            ->add('is_enabled',     null, ['required' => false])
            ->add('title',          null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('slug')
            ->add('is_inheritance', null, ['required' => false])
            ->add('position')
            ->add('parent', $taxonTreeType)
            ->add('meta', MetaFormType::class, ['label' => 'Meta tags'])
        ;

        if (!$taxon->getStructure()->isTree()) {
            $builder->remove('parent');
        }

        $structure = null;

        if (is_object($taxon) and $taxon->getStructure() instanceof UnicatStructure) {
            $structure = $taxon->getStructure();
        }

        if ($structure) {
            $properties = Yaml::parse($structure->getProperties());

            if (is_array($properties)) {
                $builder->add($builder->create(
                    'properties',
                    new TaxonPropertiesFormType($properties),
                    ['required' => false]
                ));
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->configuration->getTaxonClass(),
        ]);
    }

    public function getName()
    {
        return 'unicat_taxon_'.$this->configuration->getName();
    }
}
