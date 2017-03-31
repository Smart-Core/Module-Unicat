<?php

namespace SmartCore\Module\Unicat\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use SmartCore\Bundle\CMSBundle\Container;
use SmartCore\Bundle\MediaBundle\Service\CollectionService;
use SmartCore\Module\Unicat\Entity\UnicatAttribute;
use SmartCore\Module\Unicat\Entity\UnicatAttributesGroup;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;
use SmartCore\Module\Unicat\Entity\UnicatItemType;
use SmartCore\Module\Unicat\Entity\UnicatTaxonomy;
use SmartCore\Module\Unicat\Form\Type\AttributeFormType;
use SmartCore\Module\Unicat\Form\Type\AttributesGroupFormType;
use SmartCore\Module\Unicat\Form\Type\ItemFormType;
use SmartCore\Module\Unicat\Form\Type\TaxonomyFormType;
use SmartCore\Module\Unicat\Form\Type\TaxonCreateFormType;
use SmartCore\Module\Unicat\Form\Type\TaxonFormType;
use SmartCore\Module\Unicat\Model\AbstractValueModel;
use SmartCore\Module\Unicat\Model\ItemModel;
use SmartCore\Module\Unicat\Model\ItemRepository;
use SmartCore\Module\Unicat\Model\TaxonModel;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UnicatConfigurationManager
{
    /** @var \Doctrine\Common\Persistence\ManagerRegistry */
    protected $doctrine;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var \Symfony\Component\Form\FormFactoryInterface */
    protected $formFactory;

    /** @var \SmartCore\Bundle\MediaBundle\Service\CollectionService */
    protected $mc;

    /** @var \SmartCore\Module\Unicat\Entity\UnicatConfiguration */
    protected $configuration;

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    protected $securityToken;

    /**
     * @param ManagerRegistry $doctrine
     * @param FormFactoryInterface $formFactory
     * @param UnicatConfiguration $configuration
     * @param CollectionService $mc
     * @param TokenStorageInterface $securityToken
     */
    public function __construct(
        ManagerRegistry $doctrine,
        FormFactoryInterface $formFactory,
        UnicatConfiguration $configuration,
        CollectionService $mc,
        TokenStorageInterface $securityToken
    ) {
        $this->doctrine    = $doctrine;
        $this->em          = $doctrine->getManager();
        $this->formFactory = $formFactory;
        $this->mc          = $mc;
        $this->configuration = $configuration;
        $this->securityToken = $securityToken;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param array|null $orderBy
     *
     * @return ItemModel|null
     */
    public function findAllItems($orderBy = null)
    {
        return $this->em->getRepository($this->configuration->getItemClass())->findBy([], $orderBy);
    }

    /**
     * @param array|null $orderBy
     *
     * @return \Doctrine\ORM\Query
     */
    public function getFindAllItemsQuery($orderBy = null)
    {
        $itemEntity = $this->configuration->getItemClass();

        return $this->em->createQuery("
           SELECT i
           FROM $itemEntity AS i
           WHERE i.is_enabled = 1
           ORDER BY i.position ASC, i.id DESC
        ");
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return \Doctrine\ORM\Query
     *
     * @todo $orderBy, $limit, $offset
     */
    public function getFindItemsQuery(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $itemEntity = $this->configuration->getItemClass();
        $attributes = $this->getAttributes();

        $from = $itemEntity.' i';

        $qb = $this->em->createQueryBuilder('i');
        $qb->select('i');

        $firstWhere = true;
        foreach ($criteria as $key => $val) {
            if (isset($attributes[$key])) {
                $attr = $attributes[$key];
                $from .= ', '.$attr->getValueClassNameWithNameSpace().' '.$key;

                if ($firstWhere) {
                    $qb->where('i.id = '.$key.'.item');
                } else {
                    $qb->andWhere('i.id = '.$key.'.item');
                }

                $qb->andWhere($key.'.value = :'.$key)
                   ->setParameter($key, $val);
            }
        }

        $qb->add('from', $from);

        $firstOrderBy = true;
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $value) {
                if ($firstOrderBy) {
                    $qb->orderBy("i.$field", $value);
                    $firstOrderBy = false;
                } else {
                    $qb->addOrderBy("i.$field", $value);
                }
            }
        }

        if (!empty($limit)) {
            $qb->setMaxResults($limit);
        }

        if (!empty($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery();
    }
    /**
     * @param TaxonModel $taxon
     * @param array      $order
     *
     * @return ItemModel[]|null
     */
    public function findItemsInTaxon(TaxonModel $taxon, array $order = ['position' => 'ASC'])
    {
        return $this->getFindItemsInTaxonQuery($taxon, $order)->getResult();
    }

    /**
     * @param TaxonModel $taxon
     * @param array $order
     *
     * @return \Doctrine\ORM\Query
     *
     * @todo сделать настройку сортировки
     * @todo вынести в Repository
     */
    public function getFindItemsInTaxonQuery(TaxonModel $taxon, array $order = ['position' => 'ASC'])
    {
        $itemEntity = $this->configuration->getItemClass();

        return $this->em->createQuery("
           SELECT i
           FROM $itemEntity AS i
           JOIN i.taxonsSingle AS cs
           WHERE cs.id = :taxon
           AND i.is_enabled = 1
           ORDER BY i.position ASC, i.id DESC
        ")->setParameter('taxon', $taxon->getId());
    }

    /**
     * @param string|int $val
     * @param bool $use_item_id_as_slug
     *
     * @return ItemModel|null
     */
    public function findItem($val, $use_item_id_as_slug = true)
    {
        $key = 'slug';

        if ($use_item_id_as_slug and intval($val)) {
            $key = 'id';
        }

        return $this->em->getRepository($this->configuration->getItemClass())->findOneBy([$key => $val]);
    }

    /**
     * @param string $slug
     * @param UnicatTaxonomy $taxonomy
     *
     * @return TaxonModel[]
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findTaxonsBySlug($slug = null, UnicatTaxonomy $taxonomy = null)
    {
        $taxons = [];
        $parent = null;
        foreach (explode('/', $slug) as $taxonName) {
            if (strlen($taxonName) == 0) {
                break;
            }

            /* @var TaxonModel $taxon */
            if ($taxonomy) {
                $taxon = $this->getTaxonRepository()->findOneBy([
                    'is_enabled' => true,
                    'parent'     => $parent,
                    'slug'       => $taxonName,
                    'taxonomy'  => $taxonomy,
                ]);
            } else {
                $taxon = $this->getTaxonRepository()->findOneBy([
                    'is_enabled' => true,
                    'parent'     => $parent,
                    'slug'       => $taxonName,
                ]);
            }

            if ($taxon) {
                $taxons[] = $taxon;
                $parent = $taxon;
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $taxons;
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getTaxonRepository()
    {
        return $this->em->getRepository($this->configuration->getTaxonClass());
    }

    /**
     * @return string
     */
    public function getTaxonClass()
    {
        return $this->configuration->getTaxonClass();
    }

    /**
     * @return ItemRepository
     */
    public function getItemRepository()
    {
        return $this->em->getRepository($this->configuration->getItemClass());
    }

    /**
     * @return UnicatTaxonomy
     */
    public function getDefaultTaxonomy()
    {
        return $this->configuration->getDefaultTaxonomy();
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeCreateForm(array $options = [])
    {
        $attribute = new UnicatAttribute();
        $attribute->setUser($this->getUser());

        return $this->getAttributeForm($attribute, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']])
        ;
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeForm($data = null, array $options = [])
    {
        return $this->formFactory->create(AttributeFormType::class, $data, $options);
    }

    /**
     * @param UnicatAttribute $attribute
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeEditForm(UnicatAttribute $attribute, array $options = [])
    {
        $form = $this->getAttributeForm($attribute, $options)
            ->remove('name')
            ->remove('type')
            ->remove('items_type')
            ->remove('is_dedicated_table')
            ->remove('is_items_type_many2many')
            ->remove('update_all_records_with_default_value')
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
        ;

        $count = $this->em->getRepository($this->configuration->getItemClass())->count();
        if (empty($count)) {
            $form->add('delete', SubmitType::class, [
                'attr' => [
                    'class' => 'btn-danger',
                    'formnovalidate' => 'formnovalidate',
                    'onclick' => "return confirm('Вы уверены, что хотите удалить атрибут?')",
                ],
            ]);
        }

        $form->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);

        return $form;
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttributesGroup
     */
    public function getAttributesGroup($groupId)
    {
        return $this->em->getRepository('UnicatModule:UnicatAttributesGroup')->find($groupId);
    }

    /**
     * @param TaxonModel $data
     * @param array      $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonForm(TaxonModel $data, array $options = [])
    {
        return $this->formFactory->create(TaxonFormType::class, $data, $options);
    }

    /**
     * @param UnicatTaxonomy $taxonomy
     * @param array           $options
     * @param TaxonModel|null $parent_taxon
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonCreateForm(UnicatTaxonomy $taxonomy, array $options = [], TaxonModel $parent_taxon = null)
    {
        $class = $this->configuration->getTaxonClass();
        /** @var TaxonModel $taxon */
        $taxon = new $class();
        $taxon
            ->setTaxonomy($taxonomy)
            ->setIsInheritance($taxonomy->getIsDefaultInheritance())
            ->setUser($this->getUser())
        ;

        if ($parent_taxon) {
            $taxon->setParent($parent_taxon);
        }

        return $this->formFactory->create(TaxonCreateFormType::class, $taxon, $options)
            ->add('create', SubmitType::class, [
                'attr' => ['class' => 'btn btn-success'],
            ]);
    }

    /**
     * @param TaxonModel $taxon
     * @param array      $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonEditForm(TaxonModel $taxon, array $options = [])
    {
        return $this->getTaxonForm($taxon, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param int $id
     *
     * @return TaxonModel|null|object
     */
    public function getTaxon($id)
    {
        return $this->getTaxonRepository()->find($id);
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttribute[]
     */
    public function getAttribute($id)
    {
        return $this->em->getRepository('UnicatModule:UnicatAttribute')->find($id);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemEditForm($data = null, array $options = [])
    {
        return $this->getItemForm($data, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('delete', SubmitType::class, ['attr' => ['class' => 'btn btn-danger', 'onclick' => "return confirm('Вы уверены, что хотите удалить запись?')"]])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemForm($data = null, array $options = [])
    {
        return $this->formFactory->create(ItemFormType::class, $data, $options);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemCreateForm($data = null, array $options = [])
    {
        return $this->getItemForm($data, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyCreateForm(array $options = [])
    {
        $taxonomy = new UnicatTaxonomy();
        $taxonomy->setConfiguration($this->configuration);

        return $this->getTaxonomyForm($taxonomy, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyEditForm($data = null, array $options = [])
    {
        return $this->getTaxonomyForm($data, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getAttributesGroupCreateForm(array $options = [])
    {
        $group = new UnicatAttributesGroup();
        $group->setConfiguration($this->configuration);

        return $this->getAttributesGroupForm($group, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param mixed|null $data
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyForm($data = null, array $options = [])
    {
        return $this->formFactory->create(TaxonomyFormType::class, $data, $options);
    }

    /**
     * @param mixed|null $data
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getAttributesGroupForm($data = null, array $options = [])
    {
        return $this->formFactory->create(AttributesGroupFormType::class, $data, $options);
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
     * @return ItemModel
     */
    public function createItemEntity()
    {
        $class = $this->configuration->getItemClass();

        return new $class();
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this
     *
     * @todo события
     */
    public function createItem(FormInterface $form, Request $request)
    {
        return $this->saveItem($form, $request);
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this
     *
     * @todo события
     */
    public function updateItem(FormInterface $form, Request $request)
    {
        return $this->saveItem($form, $request);
    }

    /**
     * @param ItemModel $item
     *
     * @return $this
     *
     * @todo события
     */
    public function removeItem(ItemModel $item)
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute->isType('image') and $item->hasAttribute($attribute->getName())) {
                // @todo сделать кеширование при первом же вытаскивании данных о записи. тоже самое в saveItem(), а еще лучше выделить этот код в отельный защищенный метод.
                $tableItems = $this->em->getClassMetadata($this->configuration->getItemClass())->getTableName();
                $sql = "SELECT * FROM $tableItems WHERE id = '{$item->getId()}'";
                $res = $this->em->getConnection()->query($sql)->fetch();

                $fileId = null;
                if (!empty($res)) {
                    $previousAttributes = unserialize($res['attributes']);
                    $fileId = $previousAttributes[$attribute->getName()];
                }

                $this->mc->remove($fileId);
            }
        }

        $this->em->remove($item);
        $this->em->flush(); // Надо делать полный flush т.к. каскадом удаляются связи с категориями.

        return $this;
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this|array
     *
     * @todo запаковать в транзакцию
     */
    public function saveItem(FormInterface $form, Request $request)
    {
        /** @var ItemModel $item */
        $item = $form->getData();

        $groups = [];
        foreach ($item->getType()->getAttributesGroups() as $group) {
            $groups[] = $group->getName();
        }

        $attributes = $this->em->getRepository('UnicatModule:UnicatAttribute')->findByGroupsNames($groups);

        // Проверка и модификация атрибута. В частности загрука картинок и валидация.
        foreach ($attributes as $attribute) {
            if ($attribute->getIsDedicatedTable()) {
                continue;
            }

            if ($attribute->isType('image') and $item->hasAttribute($attribute->getName())) {
                // @todo Здесь выполняется нативный SQL т.к. ORM отдаёт скешированный - сделать через UoW.
                $tableItems = $this->em->getClassMetadata($this->configuration->getItemClass())->getTableName();
                $sql = "SELECT * FROM $tableItems WHERE id = '{$item->getId()}'";
                $res = $this->em->getConnection()->query($sql)->fetch();

                if (!empty($res)) {
                    $previousAttributes = unserialize($res['attributes']);
                    $fileId = $previousAttributes[$attribute->getName()];
                } else {
                    $fileId = null;
                }

                // удаление файла.
                $_delete_ = $request->request->get('_delete_');
                if (is_array($_delete_)
                    and isset($_delete_['attribute--'.$attribute->getName()])
                    and 'on' === $_delete_['attribute--'.$attribute->getName()]
                ) {
                    $this->mc->remove($fileId);
                    $fileId = null;
                } else {
                    $file = $item->getAttribute($attribute->getName());

                    if ($file) {
                        $this->mc->remove($fileId);
                        $fileId = $this->mc->upload($file);
                    }
                }

                $item->setAttribute($attribute->getName(), $fileId);
            } elseif ($attribute->isType('gallery')) {
                $data = json_decode($item->getAttribute($attribute->getName()), true);

                $item->setAttribute($attribute->getName(), $data);
            } elseif ($attribute->isType('unicat_item')) {
                // dummy
            }
        }

        // Удаление всех связей, чтобы потом просто назначить новые.
        $item
            ->setTaxons([])
            ->setTaxonsSingle([])
        ;

        $this->em->persist($item);
        $this->em->flush();

        // @todo если item уже существует, то сделать сохранение в один проход, но придумать как сделать обновление таксономии.

        // Вторым проходом обрабатываются атрибуты с внешних таблиц т.к. при создании новой записи нужно сгенерировать ID
        foreach ($attributes as $attribute) {
            if ($attribute->getIsDedicatedTable()) {
                $value = $item->getAttr($attribute->getName());

                $entityValueClass = $attribute->getValueClassNameWithNameSpace();

                /* @var AbstractValueModel $entityValue */
                // @todo пока допускается использование одного поля со значениями, но нужно предусмотреть и множественные.
                $entityValue = $this->em->getRepository($entityValueClass)->findOneBy(['item' => $item]);

                if (empty($entityValue)) {
                    if ($value === null) {
                        continue;
                    }

                    $entityValue= new $entityValueClass();
                    $entityValue->setItem($item);
                } elseif (!empty($entityValue) and $value === null) {
                    $this->em->remove($entityValue);
                    $this->em->flush();

                    continue;
                }

                if ($entityValue) {
                    $entityValue->setValue($value);

                    $this->em->persist($entityValue);
                    $this->em->flush();
                }
            } else {
                continue;
            }
        }

        $pd = $request->request->get($form->getName());

        $taxons = [];
        foreach ($pd as $key => $val) {
            if (false !== strpos($key, 'taxonomy--')) {
                if (is_array($val)) {
                    foreach ($val as $val2) {
                        $taxons[] = $val2;
                    }
                } else {
                    $taxons[] = $val;
                }
            }
        }

        //$request->request->set($form->getName(), $pd);
        //$taxonsCollection = $this->em->getRepository($this->getTaxonClass())->findIn($taxons);

        $taxons_ids = implode(',', $taxons);

        if (!empty($taxons_ids)) {
            // @todo убрать в Repository
            $taxonsSingle = $this->em->createQuery("
                SELECT c
                FROM {$this->getTaxonClass()} c
                WHERE c.id IN({$taxons_ids})
            ")->getResult();

            $item->setTaxonsSingle($taxonsSingle);

            $taxonsInherited = [];
            foreach ($taxonsSingle as $taxon) {
                $this->getTaxonsInherited($taxonsInherited, $taxon);
            }

            $item->setTaxons($taxonsInherited);
        }

        $this->em->persist($item);
        $this->em->flush();

        if ($item->getSlug() === null) {
            $item->setSlug($item->getId());

            $this->em->persist($item);
            $this->em->flush();
        }

        return $this;
    }

    /**
     * @param UnicatItemType|null $itemType
     *
     * @return UnicatItemType[]
     */
    public function getChildrenTypes(UnicatItemType $itemType = null)
    {
        if (empty($itemType)) {
            return [];
        }

        $attrs = $this->em->getRepository('UnicatModule:UnicatAttribute')->findBy(['items_type' => $itemType]);

        $attrGroups = [];
        foreach ($attrs as $attr) {
            foreach ($attr->getGroups() as $group) {
                $attrGroups[$group->getId()] = $group->getName();
            }
        }

        $itemTypes = [];
        foreach ($this->em->getRepository('UnicatModule:UnicatItemType')->findAll() as $itemType2) {
            foreach ($itemType2->getAttributesGroups() as $attrGroup2) {
                if (isset($attrGroups[$attrGroup2->getId()]) and $itemType->getId() !== $itemType2->getId()) {
                    $itemTypes[$itemType2->getId()] = $itemType2;
                }
            }
        }

        return $itemTypes;
    }
    
    /**
     * Рекурсивный обход всех вложенных таксонов.
     *
     * @param array      $taxonsInherited
     * @param TaxonModel $taxon
     */
    protected function getTaxonsInherited(&$taxonsInherited, TaxonModel $taxon)
    {
        if ($taxon->getParent()) {
            $this->getTaxonsInherited($taxonsInherited, $taxon->getParent());
        }

        $taxonsInherited[$taxon->getId()] = $taxon;
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttribute[]
     */
    public function getAttributes($groupId = null)
    {
        $filter = ($groupId) ? ['group' => $groupId] : [];

        $attrs = [];
        foreach ($this->em->getRepository('UnicatModule:UnicatAttribute')->findBy($filter, ['position' => 'ASC']) as $attr) {
            $attrs[$attr->getName()] = $attr;
        }

        return $attrs;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function createAttribute(UnicatAttribute $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function updateTaxon(TaxonModel $taxon)
    {
        $this->em->persist($taxon);
        $this->em->flush($taxon);

        return $this;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function updateAttribute(UnicatAttribute $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param UnicatAttributesGroup $entity
     *
     * @return $this
     */
    public function updateAttributesGroup(UnicatAttributesGroup $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param UnicatTaxonomy $entity
     *
     * @return $this
     */
    public function updateTaxonomy(UnicatTaxonomy $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @return int
     */
    protected function getUser()
    {
        if (null === $token = $this->securityToken->getToken()) {
            return 0;
        }

        if (!is_object($user = $token->getUser())) {
            return 0;
        }

        return $user;
    }
}
