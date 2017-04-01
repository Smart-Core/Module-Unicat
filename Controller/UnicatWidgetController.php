<?php

namespace SmartCore\Module\Unicat\Controller;

use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Pagerfanta\SimpleDoctrineORMAdapter;
use SmartCore\Bundle\CMSBundle\Module\CacheTrait;
use SmartCore\Bundle\CMSBundle\Module\NodeTrait;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UnicatWidgetController extends Controller
{
    use CacheTrait;
    use NodeTrait;
    use UnicatTrait;

    /** @var  int */
    protected $configuration_id;

    /**
     * @param Request  $request
     * @param string   $css_class
     * @param int      $depth
     * @param string   $template
     * @param bool     $selected_inheritance
     * @param int|null $taxonomy
     *
     * @return Response
     */
    public function taxonTreeAction(
        Request $request,
        $css_class = null,
        $depth = null,
        $template = 'knp_menu.html.twig',
        $selected_inheritance = false,
        $taxonomy = null
    ) {
        // Хак для Menu\RequestVoter
        $request->attributes->set('__selected_inheritance', $selected_inheritance);

        // @todo cache
        $taxonTree = $this->renderView('@UnicatModule/taxon_tree.html.twig', [
            'taxonClass'    => $this->unicat->getTaxonClass(),
            'css_class'     => $css_class,
            'depth'         => $depth,
            'routeName'     => 'unicat.index',
            'taxonomy'      => empty($taxonomy) ? $this->unicat->getDefaultTaxonomy() : $this->unicat->getTaxonomy($taxonomy),
            'template'      => $template,
        ]);

        $request->attributes->remove('__selected_inheritance');

        return new Response($taxonTree);
    }

    /**
     * @param null    $taxonomy
     *
     * @return JsonResponse
     */
    public function getTaxonsJsonAction($taxonomy = null)
    {
        $taxonomy = empty($taxonomy) ? $this->unicat->getDefaultTaxonomy() : $this->unicat->getTaxonomy($taxonomy);
        $taxons = [];

        if (!empty($taxonomy)) {
            $data = $this->unicat->getTaxonRepository()->findBy(['taxonomy' => $taxonomy], ['position' => 'ASC', 'id' => 'ASC']);
            /** @var TaxonModel $taxon */
            foreach ($data as $taxon) {
                $taxons[$taxon->getSlug()] = $taxon->getTitle();
            }
        }

        return new JsonResponse($taxons);
    }
    
    /**
     * @param array $criteria
     * @param array $orderBy
     * @param int   $limit
     * @param null  $offset
     *
     * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function getItemsAction(array $criteria, array $orderBy = null, $limit = 10, $offset = null)
    {
        $pagerfanta = new Pagerfanta(new SimpleDoctrineORMAdapter($this->unicat->getFindItemsQuery($criteria, $orderBy, $limit, $offset)));
        $pagerfanta->setMaxPerPage($limit);

        try {
            $pagerfanta->setCurrentPage(1);
        } catch (NotValidCurrentPageException $e) {
            return $this->createNotFoundException('Такой страницы не найдено');
        }

        return $this->render('@UnicatModule/index.html.twig', [
            'mode'          => 'list',
            'attributes'    => $this->unicat->getAttributes(),
            'configuration' => $this->unicat->getConfiguration(),
            'lastTaxon'     => null,
            'childenTaxons' => null,
            'pagerfanta'    => $pagerfanta,
            'slug'          => null,
        ]);
    }
}
