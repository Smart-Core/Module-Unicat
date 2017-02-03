<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * ORM\Entity()
 * ORM\Table(name="unicat_items_types",
 *      indexes={
 *          ORM\Index(columns={"position"}),
 *      }
 * )
 *
 * @UniqueEntity(fields={"slug"}, message="Запись с таким сегментом URI уже существует.")
 * @UniqueEntity(fields={"uuid"}, message="Запись с таким UUID уже существует.")
 */
class ItemTypeModel
{
    use ColumnTrait\Id;
    use ColumnTrait\IsEnabled;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Position;
    use ColumnTrait\FosUser;

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
     * ItemTypeModel constructor.
     */
    public function __construct()
    {
        $this->created_at   = new \DateTime();
        $this->is_enabled   = true;
        $this->position     = 0;
        $this->type         = 0;
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

}
