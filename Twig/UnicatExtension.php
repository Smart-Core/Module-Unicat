<?php

namespace SmartCore\Module\Unicat\Twig;

use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UnicatExtension extends \Twig_Extension
{
    use ContainerAwareTrait;

    /**
     * UnicatExtension constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('unicat_current_configuration',  [$this, 'getUnicatCurrentConfiguration']),
            new \Twig_SimpleFunction('unicat_get_taxons_by_taxonomy', [$this, 'getTaxonsByTaxonomy']),
        ];
    }

    /**
     * @return null|\SmartCore\Module\Unicat\Entity\UnicatConfiguration
     */
    public function getUnicatCurrentConfiguration()
    {
        return $this->container->get('unicat')->getCurrentConfiguration();
    }

    /**
     * @param int|string $id
     *
     * @return TaxonModel[]|array
     */
    public function getTaxonsByTaxonomy($id)
    {
        $unicat = $this->container->get('unicat');

        $ucm = $unicat->getCurrentConfigurationManager();

        $taxonomy = null;

        if (is_numeric($id)) {
            $taxonomy = $unicat->getTaxonomy($id);
        }

        if (empty($taxonomy)) {
            $taxonomy = $unicat->getTaxonomyRepository()->findBy(['name' => $id]);
        }

        if ($taxonomy) {
            return $ucm->getTaxonRepository()->findBy(['taxonomy' => $taxonomy], ['position' => 'ASC']);
        }

        return [];
    }
}
