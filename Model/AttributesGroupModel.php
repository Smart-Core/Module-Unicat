<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use SmartCore\Module\Unicat\Entity\UnicatConfiguration;

/**
 * ORM\Entity()
 * ORM\Table(name="unicat_attributes_groups")
 */
class AttributesGroupModel
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Name;
    use ColumnTrait\TitleNotBlank;

    /**
     * @var AttributeModel[]
     *
     * @ORM\OneToMany(targetEntity="Attribute", mappedBy="group")
     */
    protected $attributes;

    /**
     * @var TaxonModel
     *
     * @ORM\ManyToOne(targetEntity="Taxon")
     **/
    protected $taxon;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="SmartCore\Module\Unicat\Entity\UnicatConfiguration")
     **/
    protected $configuration;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->attributes = new ArrayCollection();
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function setTaxon(TaxonModel $taxon = null)
    {
        $this->taxon = $taxon;

        return $this;
    }

    /**
     * @return TaxonModel
     */
    public function getTaxon()
    {
        return $this->taxon;
    }

    /**
     * @param AttributeModel[] $attributes
     *
     * @return $this
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return AttributeModel[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param UnicatConfiguration $configuration
     *
     * @return $this
     */
    public function setConfiguration(UnicatConfiguration $configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }
}
