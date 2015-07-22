<?php

namespace Caxy\AuditLogBundle\Reader;

interface ReaderInterface
{
    public function findAllRevisions($className, $id);

    public function findRevisionsAfter($className, $id, $versionGroupId);

    public function findRevisionsBefore($className, $id, $versionGroupId);

    public function getCurrentRevision($className, $id);

    public function getRevision($className, $id, $versionGroupId);
}
