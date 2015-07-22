<?php

namespace Caxy\AuditLogBundle\Model;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class PropertyType implements PropertyTypeInterface
{
    protected $id;
    protected $property;
    protected $type;
    protected $contentRecords;

    const TEXT = 'TEXT';
    const BLOB = 'BLOB';

    /**
     * {@inheritDoc}
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * {@inheritDoc}
     */
    public function setProperty(PropertyInterface $property)
    {
        $this->property = $property;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function setType($type)
    {
        if (!in_array($type, array(self::TEXT, self::BLOB))) {
            throw new \InvalidArgumentException('Invalid type');
        }
        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Collection
     */
    public function getContentRecords()
    {
        return $this->contentRecords ?: $this->contentRecords = new ArrayCollection();
    }

    /**
     * @param ContentRecordInterface $contentRecord
     *
     * @return self
     */
    public function addContentRecord(ContentRecordInterface $contentRecord)
    {
        if (!$this->getContentRecords()->contains($contentRecord)) {
            $this->getContentRecords()->add($contentRecord);
        }

        return $this;
    }

    /**
     * @param ContentRecordInterface $contentRecord
     *
     * @return self
     */
    public function removeContentRecord(ContentRecordInterface $contentRecord)
    {
        if ($this->getContentRecords()->contains($contentRecord)) {
            $this->getContentRecords()->removeElement($contentRecord);
        }

        return $this;
    }
}
