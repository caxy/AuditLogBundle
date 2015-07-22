<?php

namespace Caxy\AuditLogBundle\Model;

class ContentRecord implements ContentRecordInterface
{
    protected $id;
    protected $propertyType;
    protected $version;
    protected $timestamp;
    protected $textContent;
    protected $blobContent;

    /**
     * {@inheritDoc}
     */
    public function getPropertyType()
    {
        return $this->propertyType;
    }

    /**
     * {@inheritDoc}
     */
    public function setPropertyType(PropertyTypeInterface $propertyType)
    {
        $this->propertyType = $propertyType;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent()
    {
        switch ($this->getPropertyType()->getType()) {
            case 'BLOB':
                return $this->getBlobContent();
            break;
            case 'TEXT':
                return $this->getTextContent();
            break;
        }

        return;
    }

    /**
     * {@inheritDoc}
     */
    public function setContent(AbstractContent $content)
    {
        switch ($this->getPropertyType()->getType()) {
            case 'BLOB':
                $this->content = $this->getBlobContent();
            break;
            case 'TEXT':
                $this->content = $this->getTextContent();
            break;
        }

        return $this;
    }

    public function getTextContent()
    {
        return $this->textContent;
    }

    public function setTextContent(TextContent $textContent)
    {
        $this->textContent = $textContent;

        return $this;
    }

    public function getBlobContent()
    {
        return $this->blobContent;
    }

    public function setBlobContent(BlobContent $blobContent)
    {
        $this->blobContent = $blobContent;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimestamp(\DateTime $timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritDoc}
     */
    public function setVersion(VersionInterface $version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }
}
