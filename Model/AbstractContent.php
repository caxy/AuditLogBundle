<?php

namespace Caxy\AuditLogBundle\Model;

abstract class AbstractContent implements ContentInterface
{
    protected $id;
    protected $content;
    protected $contentRecord;

    /**
     * {@inheritDoc}
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * {@inheritDoc}
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getContentRecord()
    {
        return $this->contentRecord;
    }

    /**
     * {@inheritDoc}
     */
    public function setContentRecord(ContentRecordInterface $contentRecord)
    {
        $this->contentRecord = $contentRecord;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }

    public function __toString()
    {
        return $this->getContent() ?: '';
    }
}
