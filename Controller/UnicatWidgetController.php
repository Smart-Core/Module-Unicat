<?php

namespace SmartCore\Module\Unicat\Controller;

use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Pagerfanta\SimpleDoctrineORMAdapter;
use SmartCore\Bundle\CMSBundle\Module\CacheTrait;
use SmartCore\Bundle\CMSBundle\Module\NodeTrait;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UnicatWidgetController extends Controller
{
    use CacheTrait;
    use NodeTrait;

    /**
     * @var int
     */
    protected $configuration_id;

    /**
     * @param Request $request
     * @param int     $depth
     * @param string  $css_class
     * @param string  $template
     * @param bool    $selected_inheritance
     * @param int     $taxonomy
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
        $ucm = $this->get('unicat')->getConfigurationManager($this->configuration_id);

        // Хак для Menu\RequestVoter
        $request->attributes->set('__selected_inheritance', $selected_inheritance);

        // @todo cache
        $taxonTree = $this->get('twig')->render('UnicatModule::taxon_tree.html.twig', [
            'taxonClass'    => $ucm->getTaxonClass(),
            'css_class'     => $css_class,
            'depth'         => $depth,
            'routeName'     => 'unicat.taxon',
            'taxonomy'     => empty($taxonomy) ? $ucm->getDefaultTaxonomy() : $ucm->getTaxonomy($taxonomy),
            'template'      => $template,
        ]);

        $request->attributes->remove('__selected_inheritance');

        return new Response($taxonTree);
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
        $ucm = $this->get('unicat')->getConfigurationManager($this->configuration_id);

        $pagerfanta = new Pagerfanta(new SimpleDoctrineORMAdapter($ucm->getFindItemsQuery($criteria, $orderBy, $limit, $offset)));
        $pagerfanta->setMaxPerPage($limit);

        try {
            $pagerfanta->setCurrentPage(1);
        } catch (NotValidCurrentPageException $e) {
            return $this->createNotFoundException('Такой страницы не найдено');
        }

        return $this->get('twig')->render('UnicatModule::items.html.twig', [
            'mode'          => 'list',
            'attributes'    => $ucm->getAttributes(),
            'configuration' => $ucm->getConfiguration(),
            'lastTaxon'     => null,
            'childenTaxons' => null,
            'pagerfanta'    => $pagerfanta,
            'slug'          => null,
        ]);
    }
}
