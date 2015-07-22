<?php

namespace Caxy\AuditLogBundle\EventListener;

use Caxy\AuditLogBundle\Manager\AuditLogManager;
use Caxy\AuditLogBundle\Service\AuditLogService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * EventListener for the Doctrine postUpdate, onFlush, postFlush, and postPersist events.
 * Uses the AuditLogService class to implement the logging of entities.
 */
class TransactionSubscriber implements EventSubscriber
{
    /**
     * @var Caxy\AuditLogBundle\Manager\AuditLogManager
     */
    protected $manager;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var Caxy\AuditLogBundle\Service\AuditLogService
     */
    protected $auditLog;

    /**
     * @param AuditLogManager $manager Injected manager service
     */
    public function __construct(AuditLogManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return array(
            Events::postUpdate,
            Events::onFlush,
            Events::postFlush,
            Events::postPersist,
            Events::postRemove,
        );
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $this->auditLog = new AuditLogService($eventArgs->getEntityManager(), $this->manager);
    }

    /**
     * @param PostFlushEventArgs $eventArgs
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        $this->auditLog->flushAuditLogs();
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $this->auditLog->saveEntityInsert($eventArgs->getEntity());
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $this->auditLog->saveEntityUpdate($eventArgs->getEntity());
    }

    public function postRemove(LifecycleEventArgs $eventArgs)
    {
        $this->auditLog->saveEntityRemoval($eventArgs->getEntity());
    }
}
