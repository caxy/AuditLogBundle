<?php

namespace Caxy\AuditLogBundle\EventListener;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\SecurityContext;
use Caxy\AuditLogBundle\Configuration\AuditLogConfiguration;

/**
 * Inject the SecurityContext user into the AuditLogConfiguration as current user.
 */
class UserSubscriber
{
    /**
     * @var AuditLogConfiguration
     */
    private $auditConfiguration;
    /**
     * @var SecurityContext
     */
    private $securityContext;

    public function __construct(AuditLogConfiguration $config, SecurityContext $context = null)
    {
        $this->auditConfiguration = $config;
        $this->securityContext = $context;
    }

    /**
     * Handles access authorization.
     *
     * @param GetResponseEvent $event An Event instance
     */
    public function handle(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        if ($this->securityContext) {
            $token = $this->securityContext->getToken();
            if ($token && $token->isAuthenticated()) {
                $this->auditConfiguration->setCurrentUser($token->getUser());
            }
        }
    }
}
