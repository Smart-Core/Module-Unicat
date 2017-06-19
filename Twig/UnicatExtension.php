<?php

namespace SmartCore\Module\Unicat\Twig;

use SmartCore\Module\Unicat\Entity\UnicatAttribute;
use SmartCore\Module\Unicat\Entity\UnicatTaxonomy;
use SmartCore\Module\Unicat\Model\ItemModel;
use SmartCore\Module\Unicat\Model\TaxonModel;
use SmartCore\Module\Unicat\Service\UnicatConfigurationManager;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UnicatExtension extends \Twig_Extension
{
    use ContainerAwareTrait;

    /**
     * Временное хранилище для рекурсии.
     *
     * @var array
     */
    protected $tmp_data = [];

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
            new \Twig_SimpleFunction('unicat_get_items',              [$this, 'getItems']),
            new \Twig_SimpleFunction('unicat_get_attr_choice_value',  [$this, 'getAttrChoiceValue']),
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
     * @param ItemModel $item
     * @param string    $attr
     *
     * @return array
     */
    public function getAttrChoiceValue(ItemModel $item, $attr)
    {
        $unicat = $this->container->get('unicat');
        $ucm = $unicat->getCurrentConfigurationManager();

        $data = null;

        $attributes = $ucm->getAttributes();

        /** @var UnicatAttribute $a */
        $a = $attributes[$attr];

        $params = $a->getParams();

        if (isset($params['form']['choices'])) {
            $params = array_flip($params['form']['choices']);

            $data = $params[$item->getAttr($attr)];
        }

        return $data;
    }

    /**
     * @param int|string $configuration
     * @param int|string $id
     * @param bool       $tree
     * @param bool       $is_array
     *
     * @return TaxonModel[]|array
     */
    public function getTaxonsByTaxonomy($configuration, $id, $tree = false, $is_array = false)
    {
        $unicat = $this->container->get('unicat');

        $ucm = $unicat->getConfigurationManager($configuration);

        $taxonomy = null;

        if (is_numeric($id)) {
            $taxonomy = $unicat->getTaxonomy($id);
        }

        if (empty($taxonomy)) {
            $taxonomy = $unicat->getTaxonomyRepository()->findOneBy(['name' => $id]);
        }

        if ($taxonomy) {
            // @todo если нода не подключена - вываливается исключение.

            if ($tree) {
                $taxons = $this->buildTaxonsTree($ucm, $taxonomy, null, $is_array);
            } else {
                $taxons = $ucm->getTaxonRepository()->findBy(['taxonomy' => $taxonomy], ['position' => 'ASC']);
            }

            return $taxons;
        }

        return [];
    }

    /**
     * @param UnicatConfigurationManager $ucm
     * @param UnicatTaxonomy  $taxonomy
     * @param TaxonModel|null $parent
     * @param bool            $is_array
     *
     * @return TaxonModel[]|array
     */
    protected function buildTaxonsTree(UnicatConfigurationManager $ucm, UnicatTaxonomy $taxonomy, TaxonModel $parent = null, $is_array = false)
    {
        $q = $ucm->getTaxonRepository()->getFindByQuery([
            'taxonomy' => $taxonomy,
            'parent' => $parent,
            'is_enabled' => true,
        ], ['position' => 'ASC']);

        if ($is_array) {
            $data = [];

            /** @var TaxonModel $taxon */
            foreach ($q->getResult() as $taxon) {

                $data[$taxon->getId()] = [
                    'id' => $taxon->getId(),
                    'title' => $taxon->getTitle(),
                    'slug' => $taxon->getSlug(),
                    'slug_full' => $taxon->getSlugFull(),
                    'meta' => $taxon->getMeta(),
                    'attrs' => $taxon->getProperties(),
                ];

                $data[$taxon->getId()]['children'] = $this->buildTaxonsTree($ucm, $taxonomy, $taxon, $is_array);
            }

            return $data;
        }

        return $q->getResult();
    }

    /**
     * @param int|string $configuration
     * @param array      $requestArray
     *
     * @return array|\SmartCore\Module\Unicat\Model\ItemModel[]
     */
    public function getItems($configuration, array $requestArray)
    {
        $ucm = $this->container->get('unicat')->getConfigurationManager($configuration);

        return $ucm->getData($requestArray);
    }
}
