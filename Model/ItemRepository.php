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

    /**
     * @param array|null $criteria
     * @param array|null $orderBy
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return \Doctrine\ORM\Query
     */
    public function getFindByQuery(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('e');

        $firstWhere = true;
        foreach ($criteria as $field => $value) {
            if ($firstWhere) {
                $qb->where("e.{$field} = :{$field}");
                $firstWhere = false;
            } else {
                $qb->andWhere("e.{$field} = :{$field}");
            }

            $qb->setParameter($field, $field);
        }

        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $order) {
                $qb->addOrderBy('e.'.$field, $order);
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
}
