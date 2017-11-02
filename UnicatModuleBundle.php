<?php

namespace SmartCore\Module\Unicat;

use Knp\Menu\MenuItem;
use SmartCore\Bundle\CMSBundle\Module\ModuleBundleTrait;
use SmartCore\Module\Unicat\DependencyInjection\Compiler\FormPass;
use SmartCore\Module\Unicat\DependencyInjection\UnicatExtension;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class UnicatModuleBundle extends Bundle
{
    use ModuleBundleTrait;

    /**
     * Получить виджеты для рабочего стола.
     *
     * @return array
     */
    public function getDashboard()
    {
        $em      = $this->container->get('doctrine.orm.default_entity_manager');
        $r       = $this->container->get('router');
        $configs = $em->getRepository(UnicatConfiguration::class)->findAll();

        $data = [
            'title' => 'Юникат',
            'items' => [],
        ];

        foreach ($configs as $config) {
            $data['items']['manage_config_'.$config->getId()] = [
                'title' => 'Конфигурация: <b>'.$config->getTitle().'</b>',
                'descr' => '',
                'url' => $r->generate('unicat_admin.configuration', ['configuration' => $config->getName()]),
            ];
        }

        return $data;
    }

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new FormPass());
    }

    /**
     * @return UnicatExtension
     */
    public function getContainerExtension()
    {
        return new UnicatExtension();
    }

    /**
     * @return array
     *
     * @todo
     */
    public function getWidgets()
    {
        return [
            'taxon_tree' => [
                'class' => 'UnicatWidget:taxonTree',
            ],
            'get_items' => [
                'class' => 'UnicatWidget:getItems',
            ],
        ];
    }

    /**
     * @return array
     */
    public function getRequiredParams()
    {
        return [
            'configuration_id',
        ];
    }

    /**
     * @param MenuItem $menu
     * @param array $extras
     *
     * @return MenuItem
     */
    public function buildAdminMenu(MenuItem $menu, array $extras = ['beforeCode' => '<i class="fa fa-angle-right"></i>'])
    {
        if ($this->hasAdmin()) {
            $extras = [
                'afterCode'  => '<i class="fa fa-angle-left pull-right"></i>',
                'beforeCode' => '<i class="fa fa-cubes"></i>',
            ];

            $submenu = $menu->addChild($this->getShortName(), ['uri' => $this->container->get('router')->generate('cms_admin_index').$this->getShortName().'/'])
                ->setAttribute('class', 'treeview')
                ->setExtras($extras)
            ;

            $submenu->setChildrenAttribute('class', 'treeview-menu');

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->container->get('doctrine.orm.entity_manager');

            foreach ($em->getRepository(UnicatConfiguration::class)->findAll() as $uc) {
                $submenu->addChild($uc->getTitle(), [
                    'route' => 'unicat_admin.configuration',
                    'routeParameters' => ['configuration' => $uc->getName()],
                ])->setExtras(['beforeCode' => '<i class="fa fa-angle-right"></i>']);
            }
        }

        return $menu;
    }
}
