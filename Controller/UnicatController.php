<?php

namespace SmartCore\Module\Unicat\Controller;

use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Controller\Controller;
use SmartCore\Bundle\CMSBundle\Module\NodeTrait;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UnicatController extends Controller
{
    use NodeTrait;
    use UnicatTrait;

    protected $configuration_id;
    protected $use_item_id_as_slug;

    /**
     * @param Request    $request
     * @param null       $slug
     * @param int|null   $page
     * @param mixed|null $options
     *
     * @return Response
     */
    public function indexAction(Request $request, $slug = null, $page = null, $options = null)
    {
        if (null === $page) {
            $page = $request->query->get('page', 1);
        }

        try {
            $requestedTaxons = $this->unicat->findTaxonsBySlug($slug, $this->unicat->getDefaultTaxonomy());
        } catch (NotFoundHttpException $e) {
            $requestedTaxons = [];
        }

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.index', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        if ($lastTaxon instanceof TaxonModel) {
            $this->get('html')->setMetas($lastTaxon->getMeta());
            $childenTaxons = $this->unicat->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'taxonomy'  => $this->unicat->getDefaultTaxonomy(),
            ], ['position' => 'ASC']);
        } else {
            $childenTaxons = $this->unicat->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'taxonomy'  => $this->unicat->getDefaultTaxonomy(),
            ], ['position' => 'ASC']);
        }

        $this->buildFrontControlForTaxon($lastTaxon);

        $pagerfanta = null;

        if ($slug) {
            if ($lastTaxon) {
                $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($this->unicat->getFindItemsInTaxonQuery($lastTaxon)));
            }
        } elseif ($this->unicat->getConfiguration()->isInheritance()) {
            $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($this->unicat->getFindAllItemsQuery()));
        }

        if (!empty($pagerfanta)) {
            $pagerfanta->setMaxPerPage($this->unicat->getConfiguration()->getItemsPerPage());

            try {
                $pagerfanta->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                throw $this->createNotFoundException('Такой страницы не найдено');
            }
        }

        return $this->render('@UnicatModule/index.html.twig', [
            'mode'          => 'list',
            'attributes'    => $this->unicat->getAttributes(),
            'configuration' => $this->unicat->getConfiguration(),
            'lastTaxon'     => $lastTaxon,
            'childenTaxons' => $childenTaxons,
            'options'       => $options,
            'pagerfanta'    => $pagerfanta,
            'slug'          => $slug,
        ]);
    }

    /**
     * @param string|null $taxonomySlug
     * @param string $itemSlug
     *
     * @return Response
     */
    public function itemAction($taxonomySlug = null, $itemSlug)
    {
        $requestedTaxons = $this->unicat->findTaxonsBySlug($taxonomySlug, $this->unicat->getDefaultTaxonomy());

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.index', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        if ($lastTaxon instanceof TaxonModel) {
            $childenTaxons = $this->unicat->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'taxonomy'   => $this->unicat->getDefaultTaxonomy(),
            ]);
        } else {
            $childenTaxons = $this->unicat->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'taxonomy'   => $this->unicat->getDefaultTaxonomy(),
            ]);
        }

        $item = $this->unicat->findItem($itemSlug, $this->use_item_id_as_slug);

        if (empty($item)) {
            throw $this->createNotFoundException();
        }

        $this->get('html')->setMetas($item->getMeta());

        $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.item', [
                'slug' => empty($lastTaxon) ? '' : $lastTaxon->getSlugFull(),
                'itemSlug' => $item->getSlug(),
            ]).'/', $item->getAttribute('title'));

        $this->node->addFrontControl('edit')
            ->setTitle('Редактировать')
            ->setUri($this->generateUrl('unicat_admin.item_edit', ['configuration' => $this->unicat->getConfiguration()->getName(), 'id' => $item->getId()]));

        return $this->render('@UnicatModule/item.html.twig', [
            'mode'          => 'view',
            'attributes'    => $this->unicat->getAttributes(),
            'item'          => $item,
//            'lastTaxon'      => $lastTaxon,
//            'childenTaxons' => $childenTaxons,
        ]);
    }

    /**
     * @param TaxonModel|false $lastTaxon
     *
     * @throws \Exception
     */
    protected function buildFrontControlForTaxon($lastTaxon = false)
    {
        $this->node->addFrontControl('create_item')
            ->setTitle('Добавить запись')
            ->setUri($this->generateUrl('unicat_admin.item_create_in_taxon', [
                'configuration'    => $this->unicat->getConfiguration()->getName(),
                'default_taxon_id' => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
            ]));

        if (!empty($lastTaxon)) {
            $this->node->addFrontControl('create_taxon')
                ->setIsDefault(false)
                ->setTitle('Создать Taxon')
                ->setUri($this->generateUrl('unicat_admin.taxonomy_with_parent_id', [
                    'configuration' => $this->unicat->getConfiguration()->getName(),
                    'parent_id'     => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
                    'id'            => $lastTaxon->getTaxonomy()->getId(),
                ]));

            $this->node->addFrontControl('edit_taxon')
                ->setIsDefault(false)
                ->setTitle('Редактировать Taxon')
                ->setUri($this->generateUrl('unicat_admin.taxon', [
                    'configuration' => $this->unicat->getConfiguration()->getName(),
                    'id'            => $lastTaxon->getId(),
                    'taxonomy_name' => $lastTaxon->getTaxonomy()->getName(),
                ]));
        }

        $this->node->addFrontControl('manage_configuration')
            ->setIsDefault(false)
            ->setTitle('Управление каталогом')
            ->setUri($this->generateUrl('unicat_admin.configuration', ['configuration' => $this->unicat->getConfiguration()->getName()]));
    }
}
