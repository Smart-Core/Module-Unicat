<?php

namespace SmartCore\Module\Unicat\Controller;

use Smart\CoreBundle\Controller\Controller;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\HttpFoundation\Request;

class AdminTaxonomyController extends Controller
{
    /**
     * @param Request $request
     * @param int     $taxonomy_id
     * @param int     $id
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function taxonEditAction(Request $request, $taxonomy_id, $id, $configuration)
    {
        $unicat = $this->get('unicat'); // @todo перевести всё на $ucm.
        $ucm    = $unicat->getConfigurationManager($configuration);

        $taxonomy = $ucm->getTaxonomy($taxonomy_id);
        $taxon     = $ucm->getTaxon($id);

        $form = $ucm->getTaxonEditForm($taxon);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToTaxonomyAdmin($ucm->getConfiguration(), $taxonomy_id);
            }

            if ($form->get('update')->isClicked() and $form->isValid()) {
                $unicat->updateTaxon($form->getData());
                $this->get('session')->getFlashBag()->add('success', 'Категория обновлена');

                return $this->redirectToTaxonomyAdmin($ucm->getConfiguration(), $taxonomy_id);
            }

            if ($form->has('delete') and $form->get('delete')->isClicked()) {
                $unicat->deleteTaxon($form->getData());
                $this->get('session')->getFlashBag()->add('success', 'Категория удалена');

                return $this->redirectToTaxonomyAdmin($ucm->getConfiguration(), $taxonomy_id);
            }
        }

        return $this->render('UnicatModule:AdminTaxonomy:taxon_edit.html.twig', [
            'configuration' => $taxonomy->getConfiguration(), // @todo убрать, это пока для наследуемого шаблона.
            'taxon'         => $taxon,
            'form'          => $form->createView(),
            'taxonomy'     => $taxonomy,
        ]);
    }

    /**
     * @param string $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($configuration)
    {
        $configuration = $this->get('unicat')->getConfiguration($configuration);

        if (empty($configuration)) {
            return $this->render('@CMS/Admin/not_found.html.twig');
        }

        return $this->render('UnicatModule:AdminTaxonomy:index.html.twig', [
            'configuration'     => $configuration,
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @param string|int $configuration
     * @param int|null $parent_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function taxonomyAction(Request $request, $id, $configuration, $parent_id = null)
    {
        $unicat     = $this->get('unicat'); // @todo перевести всё на $ucm.
        $ucm        = $unicat->getConfigurationManager($configuration);
        $taxonomy  = $unicat->getTaxonomy($id);

        $parentTaxon = $parent_id ? $ucm->getTaxonRepository()->find($parent_id) : null;

        $form = $ucm->getTaxonCreateForm($taxonomy, [], $parentTaxon);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $unicat->createTaxon($form->getData());
                $this->get('session')->getFlashBag()->add('success', 'Категория создана');

                return $this->redirectToTaxonomyAdmin($ucm->getConfiguration(), $id);
            }
        }

        return $this->render('UnicatModule:AdminTaxonomy:taxonomy.html.twig', [
            'configuration' => $taxonomy->getConfiguration(), // @todo убрать, это пока для наследуемого шаблона.
            'form'          => $form->createView(),
            'taxonomy'     => $taxonomy,
        ]);
    }

    /**
     * @param Request $request
     * @param string $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request, $configuration)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getTaxonomyCreateForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirect($this->generateUrl('unicat_admin.taxonomies_index', ['configuration' => $configuration]));
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                $ucm->updateTaxonomy($form->getData());
                $this->get('session')->getFlashBag()->add('success', 'Структура создана');

                return $this->redirect($this->generateUrl('unicat_admin.taxonomies_index', ['configuration' => $configuration]));
            }
        }

        return $this->render('UnicatModule:AdminTaxonomy:create.html.twig', [
            'form'          => $form->createView(),
            'configuration' => $ucm->getConfiguration(), // @todo убрать, это пока для наследуемого шаблона.
        ]);
    }

    /**
     * @param Request $request
     * @param int $id
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, $id, $configuration)
    {
        $ucm = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getTaxonomyEditForm($ucm->getTaxonomy($id));

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirect($this->generateUrl('unicat_admin.taxonomies_index', ['configuration' => $configuration]));
            }

            if ($form->get('update')->isClicked() and $form->isValid()) {
                $ucm->updateTaxonomy($form->getData());
                $this->get('session')->getFlashBag()->add('success', 'Структура обновлена');

                return $this->redirect($this->generateUrl('unicat_admin.taxonomies_index', ['configuration' => $configuration]));
            }
        }

        return $this->render('UnicatModule:AdminTaxonomy:edit.html.twig', [
            'form'          => $form->createView(),
            'configuration' => $ucm->getConfiguration(), // @todo убрать, это пока для наследуемого шаблона.
        ]);
    }

    /**
     * @param UnicatConfiguration $configuration
     * @param int $taxonomy_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToTaxonomyAdmin(UnicatConfiguration $configuration, $taxonomy_id)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $url = $request->query->has('redirect_to')
            ? $request->query->get('redirect_to')
            : $this->generateUrl('unicat_admin.taxonomy', ['id' => $taxonomy_id, 'configuration' => $configuration->getName()]);

        return $this->redirect($url);
    }
}
