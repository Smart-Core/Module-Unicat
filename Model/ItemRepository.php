<?php

namespace SmartCore\Module\Unicat\Model;

use Doctrine\ORM\EntityRepository;

class ItemRepository extends EntityRepository
{
    /**
     * @return int
     */
    public function count()
    {
        $qb = $this->createQueryBuilder('e')
            ->select('count(e.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
