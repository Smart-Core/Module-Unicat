<?php

namespace SmartCore\Module\Unicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 *
 *
 * @ORM\Entity()
 * @ORM\Table(name="unicat__attributes_groups",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(columns={"name", "configuration_id"}),
 *      },
 * )
 *
 * @UniqueEntity(fields={"name", "configuration"}, message="Имя должно быть уникальным.")
 */
class UnicatAttributesGroup
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Name;
    use ColumnTrait\TitleNotBlank;

    /**
     * @var UnicatAttribute[]
     *
     * @ORM\OneToMany(targetEntity="UnicatAttribute", mappedBy="group")
     */
    protected $attributes;

    /**
     * @todo подумать о привязке групп атрибутов к таксону
     *
     * @var TaxonModel
     *
     * ORM\ManyToOne(targetEntity="Taxon")
     **/
    //protected $taxon;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="UnicatConfiguration", inversedBy="attributes_groups")
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
     * @param UnicatAttribute[] $attributes
     *
     * @return $this
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return UnicatAttribute[]
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
