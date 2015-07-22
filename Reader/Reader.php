<?php

namespace Caxy\AuditLogBundle\Reader;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Doctrine\ORM\EntityManager;

class Reader
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \Caxy\AuditLogBundle\Configuration\AuditLogConfiguration
     */
    protected $config;

    /**
     * @var \Caxy\AuditLogBundle\Metadata\MetadataFactory
     */
    protected $metadataFactory;

    /**
     * @var \Caxy\AuditLogBundle\Reader\ObjectManager
     */
    protected $om;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $versionGroupRepo;

    /**
     * @param EntityManager   $em
     * @param AuditLogManager $manager
     */
    public function __construct(EntityManager $em, AuditLogManager $manager)
    {
        $this->em = $em;
        $this->config = $manager->getConfiguration();
        $this->metadataFactory = $manager->getMetadataFactory();
        $this->om = new ObjectManager($em, $manager);
        $this->versionGroupRepo = $this->em->getRepository('CaxyAuditLogBundle:VersionGroup');
    }

    /**
     * Gets all revisions for the entity identified by $className and $id. Results can be optionally limited
     * by $limit, $offset, and can be ordered by $order.
     *
     * @param string $className
     * @param int    $id
     * @param int    $limit     Default: null
     * @param int    $offset    Default: null
     * @param string $order     Accepted values: [ASC, DESC]. Default: DESC
     *
     * @return array Array of Revision objects
     */
    public function findAllRevisions($className, $id, $limit = null, $offset = null, $order = 'DESC')
    {
        $versionGroups = $this->versionGroupRepo->getAllForEntity($className, $id, $limit, $offset, $order);

        return $this->getRevisionsFromVersionGroups($className, $id, $versionGroups);
    }

    /**
     * Gets all revisions for entity after (greater than) the given version group ID. Results can be optionally limited
     * by $limit, $offset, and can be ordered by $order.
     *
     * @param string $className
     * @param int    $id
     * @param int    $versionGroupId
     * @param int    $limit          Default: null
     * @param int    $offset         Default: null
     * @param string $order          Accepted values: [ASC, DESC]. Default: DESC
     *
     * @return array Array of Revision objects
     */
    public function findRevisionsAfter($className, $id, $versionGroupId, $limit = null, $offset = null, $order = 'DESC')
    {
        $versionGroups = $this->versionGroupRepo->getAllAfter($className, $id, $versionGroupId, $limit, $offset, $order);

        return $this->getRevisionsFromVersionGroups($className, $id, $versionGroups);
    }

    /**
     * Gets all revisions for entity before (less than) the given version group ID. Results can be optionally limited
     * by $limit, $offset, and can be ordered by $order.
     *
     * @param string $className
     * @param int    $id
     * @param int    $versionGroupId
     * @param int    $limit          Default: null
     * @param int    $offset         Default: null
     * @param string $order          Accepted values: [ASC, DESC]. Default: DESC
     *
     * @return array Array of Revision objects
     */
    public function findRevisionsBefore($className, $id, $versionGroupId, $limit = null, $offset = null, $order = 'DESC')
    {
        $versionGroups = $this->versionGroupRepo->getAllBefore($class->name, $id, $versionGroupId, $limit, $offset, $order);

        return $this->getRevisionsFromVersionGroups($className, $id, $versionGroups);
    }

    /**
     * Get all revisions for entity where the given property was modified. Optionally pass in $propertyValue to filter
     * to only revisions where the given property was changed to the given value.
     *
     * Note: If $propertyValue is null, the filter is not applied at all and any revision with changes to the property
     * are returned. It does NOT filter revisions when the given property was changed to a NULL value.
     *
     * The ability to filter for a property being changed to a NULL value is not currently available.
     *
     * @param string   $className
     * @param int      $id
     * @param string   $propertyName
     * @param int|null $propertyValue
     * @param int|null $limit
     * @param int|null $offset
     * @param string   $order         Accepted values: [ASC, DESC]. Default: DESC
     *
     * @return Revision[]
     */
    public function findRevisionsByChangedProperty(
        $className,
        $id,
        $propertyName,
        $propertyValue = null,
        $limit = null,
        $offset = null,
        $order = 'DESC'
    ) {
        $versionGroups = $this->versionGroupRepo->getAllByChangedProperty(
            $className,
            $id,
            $propertyName,
            $propertyValue,
            $limit,
            $offset,
            $order
        );

        return $this->getRevisionsFromVersionGroups($className, $id, $versionGroups);
    }

    /**
     * Gets the current Revision for entity.
     *
     * @param string $className
     * @param int    $id
     *
     * @return Revision
     */
    public function getCurrentRevision($className, $id)
    {
        $versionGroup = $this->versionGroupRepo->getCurrent($className, $id);

        $class = $this->em->getClassMetadata($className);

        return $this->buildRevision($class, $id, $versionGroup);
    }

    /**
     * Gets a specific Revision for entity.
     *
     * @param string $className
     * @param int    $id
     * @param int    $versionGroupId
     *
     * @return Revision
     */
    public function getRevision($className, $id, $versionGroupId)
    {
        $class = $this->em->getClassMetadata($className);

        $versionGroup = $this->versionGroupRepo->find($versionGroupId);

        return $this->buildRevision($class, $id, $versionGroup);
    }

    /**
     * Builds an array of Revision objects for each VersionGroup in $versionGroups.
     *
     * @param string $className
     * @param int    $id
     * @param array  $versionGroups Array of VersionGroup objects.
     *
     * @return array Array of Revision objects.
     */
    private function getRevisionsFromVersionGroups($className, $id, $versionGroups)
    {
        $revisions = array();

        $class = $this->em->getClassMetadata($className);

        foreach ($versionGroups as $versionGroup) {
            $revisions[] = $this->buildRevision($class, $id, $versionGroup);
        }

        return $revisions;
    }

    /**
     * Get a Revision object for entity and $versionGroup.
     *
     * @param \Doctrine\ORM\Mapping\ClassMetadata $class
     * @param int                                 $identifier
     * @param VersionGroup                        $versionGroup
     *
     * @return Revision
     */
    private function buildRevision($class, $identifier, $versionGroup)
    {
        if (!$versionGroup) {
            return;
        }

        if (!is_array($identifier)) {
            $identifier = array($class->identifier[0] => $identifier);
        }

        $revisionClass = $this->config->getRevisionClass();

        return new $revisionClass($class, $identifier, $versionGroup, $this->om);
    }
}
