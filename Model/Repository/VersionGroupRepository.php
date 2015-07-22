<?php

namespace Caxy\AuditLogBundle\Model\Repository;

use Caxy\AuditLogBundle\Model\PropertyType;
use Doctrine\ORM\EntityRepository;

class VersionGroupRepository extends EntityRepository
{
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

    public function getAllAfter($className, $id, $versionGroupId, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);
        $qb->andWhere($qb->expr()->gt('vg.id', ':gtVersionGroupId'))
            ->setParameter('gtVersionGroupId', $versionGroupId);

        return $this->getQbResult($qb);
    }

    public function getAllBefore($className, $id, $versionGroupId, $limit = null, $offset = null, $order = 'DESC')
    {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);
        $qb->andWhere($qb->expr()->lt('vg.id', ':ltVersionGroupId'))
            ->setParameter('ltVersionGroupId', $versionGroupId);

        return $this->getQbResult($qb);
    }

    public function getAllByChangedProperty(
        $className,
        $id,
        $propertyName,
        $propertyValue = null,
        $limit = null,
        $offset = null,
        $order = 'DESC'
    ) {
        $qb = $this->getByEntityQueryBuilder($className, $id, $limit, $offset, $order);

        $qb->innerJoin('CaxyAuditLogBundle:Property', 'p', 'WITH', $qb->expr()->andX(
            $qb->expr()->eq('p.entity', 'e'),
            $qb->expr()->eq('p.name', ':property_name')
        ))
        ->innerJoin('CaxyAuditLogBundle:PropertyType', 'pt', 'WITH', $qb->expr()->andX(
            $qb->expr()->eq('pt.property', 'p'),
            $qb->expr()->eq('pt.type', ':property_type')
        ))
        ->innerJoin('CaxyAuditLogBundle:ContentRecord', 'cr', 'WITH', $qb->expr()->andX(
            $qb->expr()->eq('cr.propertyType', 'pt'),
            $qb->expr()->eq('cr.version', 'v')
        ))
        ->setParameter('property_name', $propertyName)
        ->setParameter('property_type', PropertyType::TEXT);

        if ($propertyValue !== null) {
            $qb->innerJoin('CaxyAuditLogBundle:TextContent', 'tc', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('tc.contentRecord', 'cr'),
                $qb->expr()->eq('tc.content', ':property_value')
            ))
            ->setParameter('property_value', $propertyValue);
        }

        return $this->getQbResult($qb);
    }

    public function getCountAfter($className, $id, $versionGroupId)
    {
        $qb = $this->getByEntityQueryBuilder($className, $id);
        $qb->select('count(vg.id)')
            ->andWhere($qb->expr()->gt('vg.id', ':gtVersionGroupId'))
            ->setParameter('gtVersionGroupId', $versionGroupId);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getCountBefore($className, $id, $versionGroupId)
    {
        $qb = $this->getByEntityQueryBuilder($className, $id);
        $qb->select('count(vg.id)')
            ->andWhere($qb->expr()->lt('vg.id', ':ltVersionGroupId'))
            ->setParameter('ltVersionGroupId', $versionGroupId);

        return $qb->getQuery()->getSingleScalarResult();
    }

    protected function getByEntityQueryBuilder($className, $id, $limit = null, $offset = null, $order = 'DESC')
    {
        $classNames = $this->getAllClassNames($className);

        $qb = $this->createQueryBuilder('vg');

        $qb->select('vg')
            ->innerJoin('CaxyAuditLogBundle:Version', 'v', 'WITH', $qb->expr()->eq('v.versionGroup', 'vg'))
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
            ->orderBy('vg.id', $order);

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
