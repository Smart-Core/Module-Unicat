<?php

namespace SmartCore\Module\Unicat\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Controller\Controller;
use Smart\CoreBundle\Pagerfanta\SimpleDoctrineORMAdapter;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Form\Type\ConfigurationFormType;
use SmartCore\Module\Unicat\Form\Type\ConfigurationSettingsFormType;
use SmartCore\Module\Unicat\Generator\DoctrineEntityGenerator;
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

                    $generator = new DoctrineEntityGenerator();
                    $generator->setSkeletonDirs($this->get('kernel')->getBundle('UnicatModule')->getPath().'/Resources/skeleton');
                    $siteBundle = $this->get('kernel')->getBundle('SiteBundle');
                    $targetDir  = $siteBundle->getPath().'/Entity/'.ucfirst($uc->getName());

                    if (!is_dir($targetDir) and !@mkdir($targetDir, 0777, true)) {
                        throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist and could not be created.', $targetDir));
                    }

                    $reflector = new \ReflectionClass($siteBundle);
                    $namespace = $reflector->getNamespaceName().'\Entity\\'.ucfirst($uc->getName());
                    $generator->generate($targetDir, $uc->getName(), $namespace);

                    $application = new Application($this->get('kernel'));
                    $application->setAutoExit(false);
                    $applicationInput = new ArrayInput([
                        'command' => 'doctrine:schema:update',
                        '--force' => true,
                    ]);
                    $applicationOutput = new BufferedOutput();
                    $retval = $application->run($applicationInput, $applicationOutput);

                    $uc->setEntitiesNamespace($namespace.'\\')
                        ->setUser($this->getUser())
                    ;

                    $this->persist($uc, true);

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
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $configuration
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configurationAction(Request $request, $configuration)
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');

        $ucm = $this->get('unicat')->getConfigurationManager($configuration);

        if (empty($configuration)) {
            return $this->render('@CMS/Admin/not_found.html.twig');
        }

        $pagerfanta = new Pagerfanta(new SimpleDoctrineORMAdapter(
            $ucm->getItemRepository()->getFindByQuery([], ['id' => 'DESC'])
        ));
        $pagerfanta->setMaxPerPage(20);

        try {
            $pagerfanta->setCurrentPage($request->query->get('page', 1));
        } catch (NotValidCurrentPageException $e) {
            throw $this->createNotFoundException();
        }

        return $this->render('@UnicatModule/Admin/configuration.html.twig', [
            'configuration'     => $ucm->getConfiguration(),
            'attributes'        => $em->getRepository($ucm->getConfiguration()->getAttributeClass())->findAll(),
            'pagerfanta'        => $pagerfanta, // items
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
        $configuration = $this->get('unicat')->getConfiguration($configuration);

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
            'configuration' => $configuration,
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
        $ucm  = $this->get('unicat')->getConfigurationManager($configuration);

        $newItem = $ucm->createItemEntity();
        $newItem->setUser($this->getUser());

        if ($default_taxon_id) {
            $newItem->setTaxons(new ArrayCollection([$ucm->getTaxonRepository()->find($default_taxon_id)]));
        }

        $form = $ucm->getItemCreateForm($newItem);

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                if ($form->get('cancel')->isClicked()) {
                    return $this->redirectToConfigurationAdmin($ucm->getConfiguration());
                }

                $ucm->createItem($form, $request);
                $this->addFlash('success', 'Запись создана');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration());
            }
        }

        return $this->render('@UnicatModule/Admin/item_create.html.twig', [
            'form' => $form->createView(),
            'configuration' => $ucm->getConfiguration(),
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
            if ($form->get('cancel')->isClicked()) {
                return $this->redirectToConfigurationAdmin($ucm->getConfiguration());
            }

            if ($form->get('delete')->isClicked()) {
                $ucm->removeItem($form->getData());
                $this->addFlash('success', 'Запись удалена');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration());
            }

            if ($form->isValid() and $form->get('update')->isClicked() and $form->isValid()) {
                $ucm->updateItem($form, $request);
                $this->addFlash('success', 'Запись обновлена');

                return $this->redirectToConfigurationAdmin($ucm->getConfiguration());
            }
        }

        return $this->render('@UnicatModule/Admin/item_edit.html.twig', [
            'form' => $form->createView(),
            'configuration' => $ucm->getConfiguration(),
        ]);
    }

    /**
     * @param UnicatConfiguration $configuration
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToConfigurationAdmin(UnicatConfiguration $configuration)
    {
        $request = $this->get('request_stack')->getCurrentRequest();

        $url = $request->query->has('redirect_to')
            ? $request->query->get('redirect_to')
            : $this->generateUrl('unicat_admin.configuration', ['configuration' => $configuration->getName()]);

        return $this->redirect($url);
    }
}
