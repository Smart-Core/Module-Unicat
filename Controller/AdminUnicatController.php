<?php

namespace SmartCore\Module\Unicat\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Controller\Controller;
use Smart\CoreBundle\Pagerfanta\SimpleDoctrineORMAdapter;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Entity\UnicatItemType;
use SmartCore\Module\Unicat\Form\Type\ConfigurationFormType;
use SmartCore\Module\Unicat\Form\Type\ConfigurationSettingsFormType;
use SmartCore\Module\Unicat\Form\Type\ItemTypeFormType;
use SmartCore\Module\Unicat\Generator\DoctrineEntityGenerator;
use SmartCore\Module\Unicat\Model\ItemModel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

class AdminUnicatController extends Controller
{
    use UnicatTrait;

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function indexAction(Request $request)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $form = $this->createForm(ConfigurationFormType::class);
        $form->add('create', SubmitType::class, ['attr' => ['class' => 'btn-primary']]);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                if ($form->get('create')->isClicked()) {
                    // @todo вынести в сервис генерацию сущностей

                    /** @var UnicatConfiguration $uc */
                    $uc = $form->getData();
                    $uc->setUser($this->getUser());

                    $this->persist($uc, true);

                    $this->get('unicat')->generateEntities();

                    $this->addFlash('success', 'Конфигурация <b>'.$uc->getName().'</b> создана.');
                }

                return $this->redirect($this->generateUrl('unicat_admin'));
            }
        }

        return $this->render('@UnicatModule/Admin/index.html.twig', [
            'configurations' => $em->getRepository('UnicatModule:UnicatConfiguration')->findAll(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configurationAction(Request $request, $configuration, $itemTypeId = null)
    {
        if (empty($configuration)) {
            return $this->render('@CMS/Admin/not_found.html.twig');
        }

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm = $this->get('unicat')->getConfigurationManager($configuration);

        $conf = $ucm->getConfiguration();

        if (empty($conf->getItemTypes())) {
            return $this->redirect($this->generateUrl('unicat_admin.items_types'));
        }

        // @todo валидация item type id
        if (empty($itemTypeId)) {
            foreach ($conf->getItemTypes() as $itemType) {
                $itemTypeId = $itemType->getId();

                break;
            }
        }

        $itemType = $em->find('UnicatModule:UnicatItemType', (int) $itemTypeId);

        $criteria = [];
        $parentItem = $ucm->findItem($request->query->get('parent_id', 0));

        if ($parentItem) {
            $attr = $em->getRepository('UnicatModule:UnicatAttribute')->findOneBy([
                'is_enabled' => true,
                'items_type' => $parentItem->getType(),
            ]);

            $criteria[] = [$attr->getName(), '=', $parentItem->getId()];
        }

        $direction = 'DESC';

        if (strtoupper($itemType->getOrderByDirection()) == 'DESC' or strtoupper($itemType->getOrderByDirection()) == 'ASC') {
            $direction = strtoupper($itemType->getOrderByDirection());
        }

        $orderBy = ['id' => $direction];

        /* @todo сделать сортировку по внешним таблицам через джойны, нужно чтобы колонка была NOT NULL
        if ($itemType instanceof UnicatItemType) {
            if (!empty($itemType->getOrderByAttr()) ) {
                if ($itemType->getOrderByAttr() !== 'id'
                    or $itemType->getOrderByAttr() !== 'created_at'
                    or $itemType->getOrderByAttr() !== 'position'
                ) {
                    $orderBy = [$itemType->getOrderByAttr() => $itemType->getOrderByAttr()];
                }
            }
        }
        */

        $unicatRequest = [
            'type'     => $itemType->getName(),
            'criteria' => $criteria,
            'order'    => $orderBy,
            'pager'    => [20, $request->query->get('page', 1)],
        ];

        $unicatItems = $ucm->getData($unicatRequest);

        return $this->render('@UnicatModule/Admin/configuration.html.twig', [
            'pagerfanta'    => $unicatItems['items'], // items
            'itemType'      => $itemType,
            'parentItem'    => $parentItem,
            'itemsTypesChildren' => $ucm->getChildrenTypes($itemType),
        ]);
    }

    /**
     * @param Request $request
     * @param int     $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configurationSettingsAction(Request $request, $configuration)
    {
        $ucm = $this->get('unicat')->getConfigurationManager($configuration);
        $configuration = $ucm->getConfiguration();

        if (empty($configuration)) {
            return $this->render('@CMS/Admin/not_found.html.twig');
        }

        $form = $this->createForm(ConfigurationSettingsFormType::class, $configuration);

        if (!empty($configuration->getMediaCollection())) {
            $form->remove('media_collection');
        }

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $this->persist($form->getData(), true);

                $this->addFlash('success', 'Настройки конфигурации обновлены.');

                return $this->redirect($this->generateUrl('unicat_admin.configuration.settings', ['configuration' => $configuration->getName()]));
            }
        }

        return $this->render('@UnicatModule/Admin/configuration_settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     * @param int     $default_taxon_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function itemCreateAction(Request $request, $configuration, $default_taxon_id = null)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);

        $itemType = $em->getRepository('UnicatModule:UnicatItemType')->find($request->query->get('type', 0));

        if (empty($itemType)) {
            throw new \Exception("Не указан тип записи");
        }

        $newItem = $ucm->createItemEntity();
        $newItem
            ->setUser($this->getUser())
            ->setType($itemType)
        ;

        // @todo пересмотреть таксон по умолчанию.
        if ($default_taxon_id) {
            $newItem->setTaxons(new ArrayCollection([$ucm->getTaxonRepository()->find($default_taxon_id)]));
        }

        // @todo пока можно указать только один родительский итем. сделать массив.
        $parentItem = $ucm->findItem($request->query->get('parent_id'));
        if ($parentItem) {
            $attr = $em->getRepository('UnicatModule:UnicatAttribute')->findOneBy(['items_type' => $parentItem->getType(), 'is_enabled' => true]);

            if ($attr) {
                if (method_exists($newItem, 'addAttr'.$attr->getName())) {
                    call_user_func([$newItem, 'addAttr'.$attr->getName()], $parentItem);
                } elseif (method_exists($newItem, 'setAttr'.$attr->getName())) {
                    call_user_func([$newItem, 'setAttr'.$attr->getName()], $parentItem);
                }
            }
        }

        $form = $ucm->getItemCreateForm($newItem);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                if ($form->get('cancel')->isClicked()) {
                    return $this->redirectToConfigurationAdmin($ucm->getConfiguration(), $itemType);
                }

                $ucm->createItem($form, $request);
                $this->addFlash('success', 'Запись создана');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration(), $itemType);
            }
        }

        return $this->render('@UnicatModule/Admin/item_create.html.twig', [
            'form'     => $form->createView(),
            'itemType' => $itemType,
        ]);
    }

    /**
     * @param Request $request
     * @param string $configuration
     * @param int $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function itemEditAction(Request $request, $configuration, $id)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $ucm->getItemEditForm($ucm->findItem($id));

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            $item = $form->getData();

            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToConfigurationAdmin($ucm->getConfiguration(), $item->getType());
            }

            if ($form->get('delete')->isClicked()) {
                $ucm->removeItem($form->getData());
                $this->addFlash('success', 'Запись удалена');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration(), $item->getType());
            }

            if ($form->isValid() and $form->get('update')->isClicked() and $form->isValid()) {
                /** @var ItemModel $item */

                $ucm->updateItem($form, $request);
                $this->addFlash('success', 'Запись обновлена');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration(), $item->getType());
            }
        }

        /** @var ItemModel $item */
        $item = $form->getData();

        return $this->render('@UnicatModule/Admin/item_edit.html.twig', [
            'form'           => $form->createView(),
            'itemsTypeschildren' => $ucm->getChildrenTypes($item->getType()),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function itemsTypesAction(Request $request, $configuration)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);

        return $this->render('@UnicatModule/Admin/items_types.html.twig', [
            'types' => $em->getRepository('UnicatModule:UnicatItemType')->findBy(['configuration' => $ucm->getConfiguration()], ['position' => 'ASC']),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function itemsTypeCreateAction(Request $request, $configuration)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $this->createForm(ItemTypeFormType::class);
        $form->add('create', SubmitType::class, ['attr' => ['class' => 'btn-primary']]);
        $form->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default']]);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirect($this->generateUrl('unicat_admin.items_types', ['configuration' => $configuration]));
            }

            if ($form->get('create')->isClicked() and $form->isValid()) {
                /** @var UnicatItemType $itemType */
                $itemType = $form->getData();
                $itemType
                    ->setConfiguration($ucm->getConfiguration())
                    ->setUser($this->getUser())
                ;

                $this->persist($form->getData(), true);
                $this->addFlash('success', 'Тип записей создан');

                return $this->redirect($this->generateUrl('unicat_admin.items_types', ['configuration' => $configuration]));
            }
        }

        return $this->render('@UnicatModule/Admin/items_type_create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @param string  $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function itemsTypeEditAction(Request $request, $configuration, UnicatItemType $itemType)
    {
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);
        $form = $this->createForm(ItemTypeFormType::class, $itemType);
        $form->add('update', SubmitType::class, ['attr' => ['class' => 'btn-primary']]);
        $form->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default']]);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if ($form->get('cancel')->isClicked()) {
                return $this->redirect($this->generateUrl('unicat_admin.items_types', ['configuration' => $configuration]));
            }

            if ($form->get('update')->isClicked() and $form->isValid()) {
                $this->persist($form->getData(), true);
                $this->addFlash('success', 'Тип записей обновлён');

                return $this->redirect($this->generateUrl('unicat_admin.items_types', ['configuration' => $configuration]));
            }
        }

        return $this->render('@UnicatModule/Admin/items_type_edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param UnicatConfiguration $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToConfigurationAdmin(UnicatConfiguration $configuration, UnicatItemType $itemType = null)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        if ($itemType) {
            $url = $request->query->has('redirect_to')
                ? $request->query->get('redirect_to')
                : $this->generateUrl('unicat_admin.configuration.items', ['configuration' => $configuration->getName(), 'itemTypeId' => $itemType->getId()]);

        } else {
            $url = $request->query->has('redirect_to')
                ? $request->query->get('redirect_to')
                : $this->generateUrl('unicat_admin.configuration', ['configuration' => $configuration->getName()]);
        }

        return $this->redirect($url);
    }
}
