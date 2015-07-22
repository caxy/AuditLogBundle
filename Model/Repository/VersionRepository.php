<?php

namespace Caxy\AuditLogBundle\Model\Repository;

use Doctrine\ORM\EntityRepository;

/** */
class VersionRepository extends EntityRepository
{
    public function getAllForEntityAtRevision($className, $id, $versionGroupId, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);

        $qb->andWhere($qb->expr()->eq('v.versionGroup', ':versionGroupId'))
            ->setParameter('versionGroupId', $versionGroupId);

        return $this->getQbResult($qb);
    }

    public function getAllForEntity($className, $id, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);

        return $this->getQbResult($qb);
    }

    public function getCurrent($className, $id)
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, 1);

        $result = $this->getQbResult($qb);

        if (!empty($result)) {
            return $result[0];
        }

        return false;
    }

    public function getAllAfter($className, $id, $versionId, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);
        $qb->andWhere($qb->expr()->gt('v.id', ':gtVersionId'))
            ->setParameter('gtVersionId', $versionId);

        return $this->getQbResult($qb);
    }

    public function getAllBefore($className, $id, $versionId, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);
        $qb->andWhere($qb->expr()->lt('v.id', ':ltVersionId'))
            ->setParameter('ltVersionId', $versionId);

        return $this->getQbResult($qb);
    }

    protected function getByEntityQueryBuilder($className, $id, $limit = null, $offset = null, $order = 'DESC')
    {
        $classNames = $this->getAllClassNames($className);

        $qb = $this->createQueryBuilder('v');

        $qb->select('v')
            ->innerJoin('CaxyAuditLogBundle:UpdateType', 'ut', 'WITH', $qb->expr()->eq('v.updateType', 'ut'))
            ->innerJoin('CaxyAuditLogBundle:EntityRecord', 'er', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('ut.entityRecord', 'er'),
                $qb->expr()->eq('er.loggedEntityId', ':entityId')
            ))
            ->innerJoin('CaxyAuditLogBundle:Entity', 'e', 'WITH', $qb->expr()->andX(
                $qb->expr()->in('e.name', ':classNames'),
                $qb->expr()->eq('e', 'er.entity')
            ))
            ->setParameters(array(
                'entityId' => $id,
                'classNames' => $classNames,
            ))
            ->orderBy('v.id', $order);

        if ($limit) {
            $qb->setMaxResults($limit);
            if ($offset !== null) {
                $qb->setFirstResult($offset);
            }
        }

        return $qb;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $qb
     */
    protected function getQbResult($qb)
    {
        return $qb->getQuery()->getResult();
    }

    protected function getAllClassNames($className)
    {
        $class = $this->_em->getClassMetadata($className);

        return array_merge(array($class->name), $class->parentClasses, $class->subClasses);
    }
}
