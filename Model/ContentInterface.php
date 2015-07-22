<?php

namespace Caxy\AuditLogBundle\Model;

interface ContentInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @return string
     */
    public function getContent();

    /**
     * @return ContentRecordInterface
     */
    public function getContentRecord();

    /**
     * @param ContentRecordInterface $contentRecord
     *
     * @return self
     */
    public function setContentRecord(ContentRecordInterface $contentRecord);

    /**
     * @param string $content
     *
     * @return self
     */
    public function setContent($content);
}
