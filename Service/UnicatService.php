<?php

namespace SmartCore\Module\Unicat\Service;

use Doctrine\Common\Persistence\ManagerRegistry;
use SmartCore\Bundle\MediaBundle\Service\MediaCloudService;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Entity\UnicatTaxonomy;
use SmartCore\Module\Unicat\Generator\DoctrineValueEntityGenerator;
use SmartCore\Module\Unicat\Model\AbstractTypeModel;
use SmartCore\Module\Unicat\Model\AttributeModel;
use SmartCore\Module\Unicat\Model\ItemModel;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UnicatService
{
    use ContainerAwareTrait;

    /** @var \Doctrine\Common\Persistence\ManagerRegistry */
    protected $doctrine;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var \Symfony\Component\Form\FormFactoryInterface */
    protected $formFactory;

    /** @var \SmartCore\Bundle\MediaBundle\Service\CollectionService */
    protected $mc;

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    protected $securityToken;

    /** @var UnicatConfigurationManager[] */
    protected $ucm;

    /** @var UnicatConfiguration|null */
    protected $currentConfiguration;

    /** @var UnicatConfiguration|null */
    protected static $currentConfigurationStatic;

    /**
     * @param ManagerRegistry $doctrine
     * @param FormFactoryInterface $formFactory
     * @param MediaCloudService $mediaCloud
     * @param TokenStorageInterface $securityToken
     */
    public function __construct(
        ManagerRegistry $doctrine,
        FormFactoryInterface $formFactory,
        MediaCloudService $mediaCloud,
        TokenStorageInterface $securityToken
    ) {
        $this->currentConfiguration = null;
        $this->doctrine    = $doctrine;
        $this->em          = $doctrine->getManager();
        $this->formFactory = $formFactory;
        $this->mc          = $mediaCloud->getCollection(1); // @todo настройку медиаколлекции. @important
        $this->securityToken = $securityToken;
    }

    /**
     * @param $object
     * @param bool $isFlush
     */
    protected function persist($object, $isFlush = false)
    {
        $this->em->persist($object);

        if ($isFlush) {
            $this->em->flush($object);
        }
    }

    /**
     * @param $object
     * @param bool $isFlush
     */
    protected function remove($object, $isFlush = false)
    {
        $this->em->remove($object);

        if ($isFlush) {
            $this->em->flush($object);
        }
    }

    /**
     * @param UnicatConfiguration $currentConfiguration
     *
     * @return $this
     */
    public function setCurrentConfiguration(UnicatConfiguration $currentConfiguration)
    {
        $this->currentConfiguration = $currentConfiguration;

        self::setCurrentConfigurationStatic($currentConfiguration);

        return $this;
    }

    /**
     * @return UnicatConfiguration|null
     */
    public function getCurrentConfiguration()
    {
        return $this->currentConfiguration;
    }

    /**
     * @return null|UnicatConfiguration
     */
    public static function getCurrentConfigurationStatic()
    {
        return self::$currentConfigurationStatic;
    }

    /**
     * @param null|UnicatConfiguration $currentConfigurationStatic
     *
     * @return $this
     */
    public static function setCurrentConfigurationStatic(UnicatConfiguration $currentConfigurationStatic)
    {
        self::$currentConfigurationStatic = $currentConfigurationStatic;
    }

    /**
     * @return UnicatConfigurationManager|null
     */
    public function getCurrentConfigurationManager()
    {
        return $this->currentConfiguration ? $this->getConfigurationManager($this->currentConfiguration->getId()) : null;
    }

    /**
     * @param string|int $configuration_id
     *
     * @return UnicatConfigurationManager|null
     */
    public function getConfigurationManager($configuration_id)
    {
        if (empty($configuration_id)) {
            return null;
        }

        $configuration = $this->getConfiguration($configuration_id);

        if (empty($configuration)) {
            throw new \Exception('Конфигурации "'.$configuration_id.'"" не существует');
        }

        $this->setCurrentConfiguration($configuration);

        if (!isset($this->ucm[$configuration->getId()])) {
            $this->ucm[$configuration->getId()] = new UnicatConfigurationManager($this->doctrine, $this->formFactory, $configuration, $this->mc, $this->securityToken);
        }

        return $this->ucm[$configuration->getId()];
    }

    /**
     * @param UnicatConfiguration|int $configuration
     *
     * @return AttributeModel[]
     *
     * @deprecated
     */
    public function getAttributes($configuration)
    {
        if ($configuration instanceof UnicatConfiguration) {
            $configuration = $configuration->getId();
        }

        return $this->getConfigurationManager($configuration)->getAttributes();
    }

    /**
     * @param UnicatConfiguration $configuration
     * @param int $id
     *
     * @return ItemModel|null
     *
     * @deprecated
     */
    public function getItem(UnicatConfiguration $configuration, $id)
    {
        return $this->em->getRepository($configuration->getItemClass())->find($id);
    }

    /**
     * @param UnicatConfiguration $configuration
     * @param array|null $orderBy
     *
     * @return ItemModel|null
     *
     * @deprecated
     */
    public function findAllItems(UnicatConfiguration $configuration, $orderBy = null)
    {
        return $this->em->getRepository($configuration->getItemClass())->findBy([], $orderBy);
    }

    /**
     * @param int|string $val
     *
     * @return UnicatConfiguration
     */
    public function getConfiguration($val)
    {
        $key = intval($val) ? 'id' : 'name';

        return $this->em->getRepository('UnicatModule:UnicatConfiguration')->findOneBy([$key => $val]);
    }

    /**
     * @return UnicatConfiguration[]
     */
    public function allConfigurations()
    {
        return $this->em->getRepository('UnicatModule:UnicatConfiguration')->findAll();
    }

    /**
     * @param int $id
     *
     * @return UnicatTaxonomy
     */
    public function getTaxonomy($id)
    {
        return $this->em->getRepository('UnicatModule:UnicatTaxonomy')->find($id);
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     *
     * @todo события
     */
    public function createTaxon(TaxonModel $taxon)
    {
        $this->persist($taxon, true);

        return $this;
    }

    /**
     * @param AttributeModel $entity
     *
     * @return $this
     */
    public function createAttribute(AttributeModel $entity)
    {
        if ($entity->getIsDedicatedTable()) {
            $reflector = new \ReflectionClass($entity);
            $targetDir = dirname($reflector->getFileName());

            $generator = new DoctrineValueEntityGenerator();
            $generator->setSkeletonDirs($this->container->get('kernel')->getBundle('UnicatModule')->getPath().'/Resources/skeleton');

            $generator->generate(
                $targetDir,
                $this->getCurrentConfiguration()->getName(),
                $entity->getType(),
                $entity->getValueClassName(),
                $reflector->getNamespaceName(),
                $entity->getName()
            );

            $application = new Application($this->container->get('kernel'));
            $application->setAutoExit(false);
            $applicationInput = new ArrayInput([
                'command' => 'doctrine:schema:update',
                '--force' => true,
            ]);
            $applicationOutput = new BufferedOutput();
            $retval = $application->run($applicationInput, $applicationOutput);

            $valueClass = $reflector->getNamespaceName().'\\'.$entity->getValueClassName();
        }

        $defaultValue = $entity->getUpdateAllRecordsWithDefaultValue();

        if (!empty($defaultValue) or $defaultValue == 0) {
            /** @var ItemModel $item */
            foreach ($this->getCurrentConfigurationManager()->findAllItems() as $item) {
                // @todo поддержку других типов.
                switch ($entity->getType()) {
                    case 'checkbox':
                        $defaultValue = (bool) $defaultValue;
                        break;
                    default:
                        break;
                }

                $item->setAttribute($entity->getName(), $defaultValue);

                if ($entity->getIsDedicatedTable()) {
                    /** @var AbstractTypeModel $value */
                    $value = new $valueClass();
                    $value
                        ->setItem($item)
                        ->setValue($defaultValue)
                    ;

                    $this->em->persist($value);
                }
            }
        }

        $this->em->persist($entity);
        $this->em->flush();

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function updateTaxon(TaxonModel $taxon)
    {
        $properties = $taxon->getProperties();

        foreach ($properties as $propertyName => $propertyValue) {
            if ($propertyValue instanceof UploadedFile) {
                $fileId = $this->mc->upload($propertyValue);
                $taxon->setProperty($propertyName, $fileId);
            }
        }

        $this->persist($taxon, true);

        return $this;
    }

    /**
     * @param AttributeModel $entity
     *
     * @return $this
     */
    public function updateAttribute(AttributeModel $entity)
    {
        $this->persist($entity, true);

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function deleteTaxon(TaxonModel $taxon)
    {
        throw new \Exception('@todo решить что сделать с вложенными Taxons, а также с сопряженными записями');

        $this->remove($taxon, true);

        return $this;
    }

    /**
     * @param AttributeModel $entity
     *
     * @return $this
     */
    public function deleteAttribute(AttributeModel $entity)
    {
        throw new \Exception('@todo надо решить как поступать с данными записей');

        $this->remove($entity, true);

        return $this;
    }
}
