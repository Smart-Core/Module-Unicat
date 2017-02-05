<?php

namespace SmartCore\Module\Unicat\Entity;

use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="unicat__items_types")
 */
class UnicatItemType
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Name;
    use ColumnTrait\Position;
    use ColumnTrait\TitleNotBlank;
    use ColumnTrait\FosUser;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="UnicatConfiguration", inversedBy="item_types")
     */
    protected $configuration;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->position   = 0;
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
