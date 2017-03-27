<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueIntModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="integer")
     */
    protected $value;
}
