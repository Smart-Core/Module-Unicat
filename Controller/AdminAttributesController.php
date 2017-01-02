<?php

namespace SmartCore\Module\Unicat\Controller;

use Smart\CoreBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminAttributesController extends Controller
{
    /**
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($configuration)
    {
        $em = $this->get('doctrine.orm.entity_manager');

        $configuration = $this->get('unicat')->getConfiguration($configuration);

        return $this->render('@UnicatModule/AdminAttributes/index.html.twig', [
            'configuration'     => $configuration,
            'attributes_groups' => $em->getRepository($configuration->getAttributesGroupClass())->findAll(),
            'attributes'        => $em->getRepository($configuration->getAttributeClass())->findAll(),

        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createGroupAction(Request $request, $configuration)
    {
        $urm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $urm->getAttributesGroupCreateForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                $urm->updateAttributesGroup($form->getData());
                $this->addFlash('success', 'Группа атрибутов создана');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/create_group.html.twig', [
            'form'          => $form->createView(),
            'configuration' => $urm->getConfiguration(),
        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     * @param int        $group_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function groupAction(Request $request, $configuration, $group_id)
    {
        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);
        $form   = $ucm->getAttributeCreateForm($group_id);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $unicat->createAttribute($form->getData());
                $this->addFlash('success', 'Свойство создано');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $unicat->getCurrentConfiguration()->getName(), 'group_id' => $group_id]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/group.html.twig', [
            'form'       => $form->createView(),
            'attributes' => $unicat->getAttributes($configuration),
            'group'      => $ucm->getAttributesGroup($group_id),
            'configuration' => $unicat->getCurrentConfiguration(),
        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     * @param int        $group_id
     * @param int        $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, $configuration, $group_id, $id)
    {
        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);
        $form   = $ucm->getAttributeEditForm($ucm->getAttribute($id));

        $configuration = $ucm->getConfiguration();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->has('cancel') and $form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_id' => $group_id]);
            }

            if ($form->has('update') and $form->get('update')->isClicked() and $form->isValid()) {
                $unicat->updateAttribute($form->getData());
                $this->addFlash('success', 'Атрибут обновлён');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_id' => $group_id]);
            }

            if ($form->has('delete') and $form->get('delete')->isClicked()) {
                $unicat->deleteAttribute($form->getData());
                $this->addFlash('success', 'Атрибут удалён');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_id' => $group_id]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/edit.html.twig', [
            'form' => $form->createView(),
            'configuration' => $configuration,
        ]);
    }
}
