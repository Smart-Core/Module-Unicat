<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
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
     * @ORM\Column(type="string", length=100, unique=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=64, unique=true, nullable=true)
     */
    protected $uuid;

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
     * @todo вспомнить для чего тип ;)
     *
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default":0})
     */
    protected $type;

    /**
     * Constructor.
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
        if (false !== strpos($name, 'structure:')) {
            $structureName = str_replace('structure:', '', $name);

            if ($this->taxons->count() > 0) {
                $structureCollection = new ArrayCollection();

                foreach ($this->taxons as $taxon) {
                    if ($taxon->getStructure()->getName() == $structureName) {
                        $structureCollection->add($taxon);
                    }
                }

                return $structureCollection;
            }
        }

        if (false !== strpos($name, 'attribute:')) {
            $attributeName = str_replace('attribute:', '', $name);

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
        if (false !== strpos($name, 'attribute:')) {
            $this->attributes[str_replace('attribute:', '', $name)] = $value;
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
        return (string) $this->getId().': '.$this->getSlug();
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
        return (isset($this->attributes[$name]) or null === @$this->attributes[$name]) ? true : false;
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
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
}
