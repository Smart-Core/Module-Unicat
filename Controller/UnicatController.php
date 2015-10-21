<?php

namespace SmartCore\Module\Unicat\Controller;

use Knp\RadBundle\Controller\Controller;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use SmartCore\Bundle\CMSBundle\Module\NodeTrait;
use SmartCore\Module\Unicat\Model\TaxonModel;
use SmartCore\Module\Unicat\Service\UnicatConfigurationManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UnicatController extends Controller
{
    use NodeTrait;

    protected $configuration_id;
    protected $use_item_id_as_slug;

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        return $this->taxonAction($request);
    }

    /**
     * @param Request  $request
     * @param null     $slug
     * @param int|null $page
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function taxonAction(Request $request, $slug = null, $page = null)
    {
        if (null === $page) {
            $page = $request->query->get('page', 1);
        }

        $ucm = $this->get('unicat')->getConfigurationManager($this->configuration_id);

        $requestedTaxons = $ucm->findTaxonsBySlug($slug, $ucm->getDefaultStructure());

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.taxon', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        if ($lastTaxon instanceof TaxonModel) {
            $this->get('html')->setMetas($lastTaxon->getMeta());
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'structure'  => $ucm->getDefaultStructure(),
            ], ['position' => 'ASC']);
        } else {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'structure'  => $ucm->getDefaultStructure(),
            ], ['position' => 'ASC']);
        }

        $this->buildFrontControlForTaxon($ucm, $lastTaxon);

        $pagerfanta = null;

        if ($slug) {
            if ($lastTaxon) {
                $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($ucm->getFindItemsInTaxonQuery($lastTaxon)));
            }
        } elseif ($ucm->getConfiguration()->isInheritance()) {
            $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($ucm->getFindAllItemsQuery()));
        }

        if (!empty($pagerfanta)) {
            $pagerfanta->setMaxPerPage($ucm->getConfiguration()->getItemsPerPage());

            try {
                $pagerfanta->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                return $this->createNotFoundException('Такой страницы не найдено');
            }
        }

        return $this->render('UnicatModule::items.html.twig', [
            'mode'          => 'list',
            'attributes'    => $ucm->getAttributes(),
            'configuration' => $ucm->getConfiguration(),
            'lastTaxon'     => $lastTaxon,
            'childenTaxons' => $childenTaxons,
            'pagerfanta'    => $pagerfanta,
            'slug'          => $slug,
        ]);
    }

    /**
     * @param UnicatConfigurationManager $ucm
     * @param TaxonModel|false           $lastTaxon
     *
     * @throws \Exception
     */
    protected function buildFrontControlForTaxon(UnicatConfigurationManager $ucm, $lastTaxon = false)
    {
        $this->node->addFrontControl('create_item')
            ->setTitle('Добавить запись')
            ->setUri($this->generateUrl('unicat_admin.item_create_in_taxon', [
                'configuration'    => $ucm->getConfiguration()->getName(),
                'default_taxon_id' => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
            ]));

        if (!empty($lastTaxon)) {
            $this->node->addFrontControl('create_taxon')
                ->setIsDefault(false)
                ->setTitle('Создать Taxon')
                ->setUri($this->generateUrl('unicat_admin.structure_with_parent_id', [
                    'configuration' => $ucm->getConfiguration()->getName(),
                    'parent_id'     => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
                    'id'            => $lastTaxon->getStructure()->getId(),
                ]));

            $this->node->addFrontControl('edit_taxon')
                ->setIsDefault(false)
                ->setTitle('Редактировать Taxon')
                ->setUri($this->generateUrl('unicat_admin.taxon', [
                    'configuration' => $ucm->getConfiguration()->getName(),
                    'id'            => $lastTaxon->getId(),
                    'structure_id'  => $lastTaxon->getStructure()->getId(),
                ]));
        }

        $this->node->addFrontControl('manage_configuration')
            ->setIsDefault(false)
            ->setTitle('Управление каталогом')
            ->setUri($this->generateUrl('unicat_admin.configuration', ['configuration' => $ucm->getConfiguration()->getName()]));
    }

    /**
     * @param string|null $structureSlug
     * @param string $itemSlug
     *
     * @return Response
     */
    public function itemAction($structureSlug = null, $itemSlug)
    {
        $ucm = $this->get('unicat')->getConfigurationManager($this->configuration_id);

        $requestedTaxons = $ucm->findTaxonsBySlug($structureSlug, $ucm->getDefaultStructure());

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.taxon', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        if ($lastTaxon instanceof TaxonModel) {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'structure'  => $ucm->getDefaultStructure(),
            ]);
        } else {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'structure'  => $ucm->getDefaultStructure(),
            ]);
        }

        $item = $ucm->findItem($itemSlug, $this->use_item_id_as_slug);

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
            ->setUri($this->generateUrl('unicat_admin.item_edit', ['configuration' => $ucm->getConfiguration()->getName(), 'id' => $item->getId()]));

        return $this->render('UnicatModule::item.html.twig', [
            'mode'          => 'view',
            'attributes'    => $ucm->getAttributes(),
            'item'          => $item,
//            'lastTaxon'      => $lastTaxon,
//            'childenTaxons' => $childenTaxons,
        ]);
    }
}
