<?php

namespace SmartCore\Module\Unicat\Form\Tree;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonTreeType extends DoctrineType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $choiceLoader = function (Options $options) {
            // Unless the choices are given explicitly, load them on demand
            if (null === $options['choices']) {
                $hash = null;
                $qbParts = null;

                // If there is no QueryBuilder we can safely cache DoctrineChoiceLoader,
                // also if concrete Type can return important QueryBuilder parts to generate
                // hash key we go for it as well
                if (!$options['query_builder'] || false !== ($qbParts = $this->getQueryBuilderPartsForCachingHash($options['query_builder']))) {
                    $hash = CachingFactoryDecorator::generateHash(array(
                        $options['em'],
                        $options['class'],
                        $qbParts,
                    ));

                    if (isset($this->choiceLoaders[$hash])) {
                        return $this->choiceLoaders[$hash];
                    }
                }

                if (null !== $options['query_builder']) {
                    $entityLoader = $this->getLoader($options['em'], $options['query_builder'], $options['class']);
                } else {
                    $queryBuilder = $options['em']->getRepository($options['class'])->createQueryBuilder('e');
                    $entityLoader = $this->getLoader($options['em'], $queryBuilder, $options['class']);
                }

                // Inject option 'unicat_taxonomy'
                $entityLoader->setTaxonomy($options['unicat_taxonomy']);

                $doctrineChoiceLoader = new DoctrineChoiceLoader(
                    $options['em'],
                    $options['class'],
                    $options['id_reader'],
                    $entityLoader
                );

                if ($hash !== null) {
                    $this->choiceLoaders[$hash] = $doctrineChoiceLoader;
                }

                return $doctrineChoiceLoader;
            }
        };

        $resolver->setDefaults([
            'choice_label'  => 'form_title',
            'class'         => function (Options $options) {
                return $options['unicat_taxonomy']->getConfiguration()->getTaxonClass();
            },
            'choice_loader' => $choiceLoader,
            'required'      => false,
            'unicat_taxonomy' => null, // SmartCore\Module\Unicat\Entity\UnicatTaxonomy
        ]);
    }

    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        return new TaxonLoader($manager, $queryBuilder, $class);
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_tree';
    }
}
