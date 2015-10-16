<?php

namespace SmartCore\Module\Unicat\Form\Tree;

use Doctrine\Common\Persistence\ObjectManager;
use SmartCore\Module\Unicat\Entity\UnicatStructure;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonTreeType extends DoctrineType
{
    /**
     * @var UnicatStructure
     */
    protected $structure;

    /**
     * @param UnicatStructure $structure
     *
     * @return $this
     */
    public function setStructure(UnicatStructure $structure)
    {
        $this->structure = $structure;

        return $this;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $loader = function (Options $options) {
            return $this->getLoader($options['em'], $options['query_builder'], $options['class']);
        };

        $resolver->setDefaults([
            'choice_label' => 'form_title',
            'class'        => $this->structure->getConfiguration()->getTaxonClass(),
            'loader'       => $loader,
            'required'     => false,
        ]);
    }

    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        return new TaxonLoader($manager, $this->structure, $class);
    }

    public function getName()
    {
        return 'unicat_taxon_tree';
    }
}
