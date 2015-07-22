<?php

namespace Caxy\AuditLogBundle\Model;

interface ContentRecordInterface
{
    /**
     * @return PropertyTypeInterface
     */
    public function getPropertyType();

    /**
     * @param PropertyTypeInterface $propertyType
     *
     * @return self
     */
    public function setPropertyType(PropertyTypeInterface $propertyType);

    /**
     * @return AbstractContent
     */
    public function getContent();

    /**
     * @param AbstractContent $content
     *
     * @return self
     */
    public function setContent(AbstractContent $content);

    /**
     * @return \DateTime
     */
    public function getTimestamp();

    /**
     * @param \DateTime $timestamp
     *
     * @return self
     */
    public function setTimestamp(\DateTime $timestamp);

    /**
     * @return VersionInterface
     */
    public function getVersion();

    /**
     * @param VersionInterface $version
     *
     * @return self
     */
    public function setVersion(VersionInterface $version);

    /**
     * @return int
     */
    public function getId();
}
