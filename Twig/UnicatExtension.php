<?php

namespace SmartCore\Module\Unicat\Twig;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UnicatExtension extends \Twig_Extension
{
    use ContainerAwareTrait;

    /**
     * UnicatExtension constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('unicat_current_configuration', [$this, 'getUnicatCurrentConfiguration']),
        ];
    }

    /**
     * @return null|\SmartCore\Module\Unicat\Entity\UnicatConfiguration
     */
    public function getUnicatCurrentConfiguration()
    {
        return $this->container->get('unicat')->getCurrentConfiguration();
    }
}
