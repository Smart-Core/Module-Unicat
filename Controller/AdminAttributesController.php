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
        return $this->render('@UnicatModule/AdminAttributes/index.html.twig', [
            'configuration' => $this->get('unicat')->getConfiguration($configuration),
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
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getAttributesGroupCreateForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                $ucm->updateAttributesGroup($form->getData());
                $this->addFlash('success', 'Группа атрибутов создана');

                return $this->redirectToRoute('unicat_admin.attributes_index', ['configuration' => $configuration]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/create_group.html.twig', [
            'form'          => $form->createView(),
        ]);
    }

    /**
     * @param Request    $request
     * @param string|int $configuration
     * @param string     $group_name
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function groupAction(Request $request, $configuration, $group_name)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);
        $group  = $em->getRepository('UnicatModule:UnicatAttributesGroup')->findOneBy(['name' => $group_name, 'configuration' => $unicat->getCurrentConfiguration()]);

        $form   = $ucm->getAttributeCreateForm($group->getId());

        $configuration = $ucm->getConfiguration();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $unicat->createAttribute($form->getData());
                $this->addFlash('success', 'Свойство создано');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_name' => $group_name]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/group.html.twig', [
            'form'       => $form->createView(),
            'attributes' => $em->getRepository('UnicatModule:UnicatAttribute')->findBy(['group' => $group->getId()]),
            'group'      => $group,
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
    public function editAction(Request $request, $configuration, $group_name, $name)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $unicat = $this->get('unicat');
        $ucm    = $unicat->getConfigurationManager($configuration);

        $attribute = $em->getRepository('UnicatModule:UnicatAttribute')->findOneBy(['name' => $name, 'configuration' => $unicat->getCurrentConfiguration()]);

        $form   = $ucm->getAttributeEditForm($attribute);

        $configuration = $ucm->getConfiguration();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->has('cancel') and $form->get('cancel')->isClicked()) {
                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_name' => $group_name]);
            }

            if ($form->has('update') and $form->get('update')->isClicked() and $form->isValid()) {
                $unicat->updateAttribute($form->getData());
                $this->addFlash('success', 'Атрибут обновлён');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_name' => $group_name]);
            }

            if ($form->has('delete') and $form->get('delete')->isClicked()) {
                $unicat->deleteAttribute($form->getData());
                $this->addFlash('success', 'Атрибут удалён');

                return $this->redirectToRoute('unicat_admin.attributes', ['configuration' => $configuration->getName(), 'group_name' => $group_name]);
            }
        }

        return $this->render('@UnicatModule/AdminAttributes/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
