<?php

namespace SmartCore\Module\Unicat\Command;

use Smart\CoreBundle\Utils\OutputWritelnTrait;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class GenerateEntitiesCommand extends ContainerAwareCommand
{
    use OutputWritelnTrait;

    protected function configure()
    {
        $this
            ->setName('smart:unicat:generate-entities')
            ->setDescription('Generate entities.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;  // для OutputWritelnTrait
        $this->output = $output; // для OutputWritelnTrait

        /** @var \Doctrine\ORM\EntityManager $em */
        $em         = $this->getContainer()->get('doctrine.orm.entity_manager');
        $finder     = new Finder();
        $filesystem = new Filesystem();
        /** @var \AppKernel $kernel */
        $kernel      = $this->getContainer()->get('kernel');
        $entitiesDir = $kernel->getBundle('SiteBundle')->getPath().'/Entity';


        $unicat = $this->getContainer()->get('unicat');

        $unicat->generateEntities();

    }
}
