<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use SmartCore\Module\Unicat\Entity\UnicatItemType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * ORM\Entity()
 * ORM\Table(name="unicat_items",
 *      indexes={
 *          ORM\Index(columns={"position"}),
 *      }
 * )
 *
 * @UniqueEntity(fields={"slug"}, message="Запись с таким сегментом URI уже существует.")
 * @UniqueEntity(fields={"uuid"}, message="Запись с таким UUID уже существует.")
 */
class ItemModel
{
    use ColumnTrait\Id;
    use ColumnTrait\IsEnabled;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\UpdatedAt;
    use ColumnTrait\Position;
    use ColumnTrait\FosUser;

    /**
     * @var TaxonModel[]
     *
     * ORM\ManyToMany(targetEntity="Taxon", inversedBy="items", cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * ORM\JoinTable(name="unicat_items_taxons_relations")
     */
    protected $taxons;

    /**
     * @var TaxonModel[]
     *
     * ORM\ManyToMany(targetEntity="Taxon", inversedBy="itemsSingle", cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * ORM\JoinTable(name="unicat_items_taxons_relations_single")
     */
    protected $taxonsSingle;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=100, unique=true, nullable=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=64, unique=true, nullable=true)
     */
    protected $uuid;

    /**
     * Скрытые дополнительные данные. Используется для яваскрипт кастомизации формы.
     *
     * @var string|null
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $hidden_extra;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    protected $meta;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    protected $attributes;

    /**
     * @var UnicatItemType
     *
     * @ORM\ManyToOne(targetEntity="SmartCore\Module\Unicat\Entity\UnicatItemType", fetch="EXTRA_LAZY")
     */
    protected $type;

    /**
     * ItemModel constructor.
     */
    public function __construct()
    {
        $this->taxons       = new ArrayCollection();
        $this->taxonsSingle = new ArrayCollection();
        $this->created_at   = new \DateTime();
        $this->is_enabled   = true;
        $this->position     = 0;
        $this->type         = 0;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (false !== strpos($name, 'taxonomy--')) {
            $taxonomyName = str_replace('taxonomy--', '', $name);

            if ($this->taxons->count() > 0) {
                $taxonomyCollection = new ArrayCollection();

                foreach ($this->taxons as $taxon) {
                    if ($taxon->getTaxonomy()->getName() == $taxonomyName) {
                        $taxonomyCollection->add($taxon);
                    }
                }

                return $taxonomyCollection;
            }
        }

        if (false !== strpos($name, 'attribute--')) {
            $attributeName = str_replace('attribute--', '', $name);

            if (isset($this->attributes[$attributeName])) {
                return $this->attributes[$attributeName];
            }
        }

        return;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function __set($name, $value)
    {
        if (false !== strpos($name, 'attribute--')) {
            $this->attributes[str_replace('attribute--', '', $name)] = $value;
        }

        return $this;
    }

    /**
     * @see getName
     *
     * @return string
     */
    public function __toString()
    {
        $toStringPattern = $this->getType()->getToStringPattern();

        if (!empty($toStringPattern)) {
            preg_match_all('/\[\[(.+?)\]\]/', $this->getType()->getToStringPattern(), $matches);

            if (isset($matches[1]) and !empty($matches[1])) {
                $toStringPattern = str_replace('[[id]]', $this->getId(), $toStringPattern);

                foreach ($matches[1] as $match) {
                    $toStringPattern = str_replace('[['.$match.']]', $this->getAttribute($match), $toStringPattern);
                }

                return (string) $toStringPattern;
            }
        }

        return (string) 'id'.$this->getId().': '.$this->getSlug();
    }

    /**
     * @ORM\PreUpdate()
     */
    public function doStuffOnPreUpdate()
    {
        $this->updated_at = new \DateTime();
    }

    /**
     * Получение всех родительских итемов.
     *
     * @return ItemModel[]
     */
    public function getParentItems()
    {
        $data = [];

        foreach ($this as $key => $value) {
            if (strpos($key, 'attr_') === 0 and $value instanceof ItemModel) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
    
    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function addTaxon(TaxonModel $taxon)
    {
        if (!$this->taxons->contains($taxon)) {
            $this->taxons->add($taxon);
        }

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function removeTaxon(TaxonModel $taxon)
    {
        if (!$this->taxons->contains($taxon)) {
            $this->taxons->removeElement($taxon);
        }

        return $this;
    }

    /**
     * @return TaxonModel[]
     */
    public function getTaxons()
    {
        return $this->taxons;
    }

    /**
     * @param TaxonModel[]|ArrayCollection $taxons
     *
     * @return $this
     */
    public function setTaxons($taxons)
    {
        $this->taxons = $taxons;

        return $this;
    }

    /**
     * @param TaxonModel[] $taxonsSingle
     *
     * @return $this
     */
    public function setTaxonsSingle($taxonsSingle)
    {
        $this->taxonsSingle = $taxonsSingle;

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function addTaxonSingle(TaxonModel $taxon)
    {
        if (!$this->taxonsSingle->contains($taxon)) {
            $this->taxonsSingle->add($taxon);
        }

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function removeTaxonSingle(TaxonModel $taxon)
    {
        if (!$this->taxonsSingle->contains($taxon)) {
            $this->taxonsSingle->removeElement($taxon);
        }

        return $this;
    }

    /**
     * @return TaxonModel[]
     */
    public function getTaxonsSingle()
    {
        return $this->taxonsSingle;
    }

    /**
     * @param array $attributes
     *
     * @return $this
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        if (method_exists($this, 'getAttr'.$name)) {
            return call_user_func([$this, 'getAttr'.$name]);
        }

        return (isset($this->attributes[$name])) ? $this->attributes[$name] : $default;
    }

    /**
     * Short alias for getAttribute.
     *
     * @param string     $name
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getAttr($name, $default = null)
    {
        return $this->getAttribute($name, $default);
    }

    /**
     * Short alias for hasAttribute.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function hasAttr($name)
    {
        return $this->hasAttribute($name);
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return (
            method_exists($this, 'getAttr'.$name)
            or isset($this->attributes[$name])
            or null === @$this->attributes[$name]
        ) ? true : false;
    }

    /**
     * @param string $slug
     *
     * @return $this
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param array $meta
     *
     * @return $this
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * @return array
     */
    public function getMeta()
    {
        return empty($this->meta) ? [] : $this->meta;
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return $this
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return UnicatItemType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param UnicatItemType $type
     *
     * @return $this
     */
    public function setType(UnicatItemType $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getHiddenExtra()
    {
        return $this->hidden_extra;
    }

    /**
     * @param string $hidden_extra
     *
     * @return $this
     */
    public function setHiddenExtra($hidden_extra)
    {
        $this->hidden_extra = $hidden_extra;

        return $this;
    }
}
