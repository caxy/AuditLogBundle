<?php

namespace Caxy\AuditLogBundle\Reader\Collection;

use Caxy\AuditLogBundle\Reader\ObjectManager;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Based on Doctrine ORM 2.3 class \Doctrine\ORM\PersistentCollection.
 * Since Doctrine's class is marked as final, was unable to extend the
 * class and instead was forced fork it.
 *
 * A PersistentCollection represents a collection of elements that have persistent state.
 *
 * The purpose of this custom implementation is to remove the UnitOfWork from the functions,
 * and to use our custom entity persisters when initializing the collection (lazy-loading).
 *
 * There is a todo tag on the Doctrine class to allow for inheritance on the class, but until
 * that is done we have to use our complete custom class.
 */
class PersistentCollection implements Collection, Selectable
{
    private $snapshot = array();

    private $owner;

    private $association;

    private $em;

    private $om;

    private $backRefFieldName;

    private $typeClass;

    private $isDirty = false;

    private $initialized = true;

    private $coll;

    private $versionGroupId;

    /**
     * @param ClassMetadata $class
     * @param \Doctrine\Common\Collections\ArrayCollection $coll
     * @param integer $versionGroupId
     */
    public function __construct(EntityManager $em, $class, $coll, ObjectManager $om, $versionGroupId)
    {
        $this->coll = $coll;
        $this->em = $em;
        $this->typeClass = $class;
        $this->om = $om;
        $this->versionGroupId = $versionGroupId;
    }

    public function getVersionGroupId()
    {
        return $this->versionGroupId;
    }

    public function setVersionGroupId($versionGroupId)
    {
        $this->versionGroupId = $versionGroupId;
    }

    public function setOwner($entity, array $assoc)
    {
        $this->owner = $entity;
        $this->association = $assoc;
        $this->backRefFieldName = $assoc['inversedBy'] ?: $assoc['mappedBy'];
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function getTypeClass()
    {
        return $this->typeClass;
    }

    public function hydrateAdd($element)
    {
        $this->coll->add($element);

        // If backRefFieldName is set and it's a one-to-many association,
        // we need to set the back reference
        if ($this->backRefFieldName && $this->association['type'] === ClassMetadata::ONE_TO_MANY) {
            // Set back reference to owner
            $this->typeClass->reflFields[$this->backRefFieldName]->setValue(
                $element, $this->owner
            );
        }
    }

    public function hydrateSet($key, $element)
    {
        $this->coll->set($key, $element);

        // If backRefFieldName is set, then the association is bidirectional
        // and we need to set the back reference
        if ($this->backRefFieldName && $this->association['type'] === ClassMetadata::ONE_TO_MANY) {
            // Set back reference to owner
            $this->typeClass->reflFields[$this->backRefFieldName]->setValue(
                $element, $this->owner
            );
        }
    }

    public function initialize()
    {
        if ($this->initialized || !$this->association) {
            return;
        }

        // Has NEW objects added through add(). Remember them.
        $newObjects = array();

        if ($this->isDirty) {
            $newObjects = $this->coll->toArray();
        }

        $this->coll->clear();
        $this->om->loadCollection($this);
        $this->takeSnapshot();

        // Reattach NEW objects added through add(), if any.
        if ($newObjects) {
            foreach ($newObjects as $obj) {
                $this->coll->add($obj);
            }

            $this->isDirty = true;
        }

        $this->initialized = true;
    }

    public function takeSnapshot()
    {
        $this->snapshot = $this->coll->toArray();
        $this->isDirty = false;
    }

    public function getSnapshot()
    {
        return $this->snapshot;
    }

    /**
     * INTERNAL: Gets the association mapping of the collection.
     *
     * @return array
     */
    public function getMapping()
    {
        return $this->association;
    }

    /**
     * Marks this collection as changed / dirty.
     */
    private function changed()
    {
        if ($this->isDirty) {
            return;
        }

        $this->isDirty = true;
    }

    public function isDirty()
    {
        return $this->isDirty;
    }

    public function setDirty($dirty)
    {
        $this->isDirty = $dirty;
    }

    /**
     * @param boolean $bool
     */
    public function setInitialized($bool)
    {
        $this->initialized = $bool;
    }

    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $this->initialize();

        return $this->coll->first();
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $this->initialize();

        return $this->coll->last();
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        $this->initialize();

        $removed = $this->coll->remove($key);

        if (!$removed) {
            return $removed;
        }

        $this->changed();

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement($element)
    {
        $this->initialize();

        $removed = $this->coll->removeElement($element);

        if (!$removed) {
            return $removed;
        }

        $this->changed();

        return $removed;
    }

    /**
     * {@inheritDoc}
     */
    public function containsKey($key)
    {
        $this->initialize();

        return $this->coll->containsKey($key);
    }

    /**
     * {@inheritDoc}
     *
     * @todo Implement FETCH_EXTRA_LAZY contains
     */
    public function contains($element)
    {
        $this->initialize();

        return $this->coll->contains($element);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Closure $p)
    {
        $this->initialize();

        return $this->coll->exists($p);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        $this->initialize();

        return $this->coll->indexOf($element);
    }

    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        $this->initialize();

        return $this->coll->get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        $this->initialize();

        return $this->coll->getKeys();
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        $this->initialize();

        return $this->coll->getValues();
    }

    /**
     * {@inheritDoc}
     *
     * @todo Implement FETCH_EXTRA_LAZY count
     */
    public function count()
    {
        $this->initialize();

        return $this->coll->count();
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value)
    {
        $this->initialize();

        $this->coll->set($key, $value);

        $this->changed();
    }

    /**
     * {@inheritDoc}
     */
    public function add($value)
    {
        $this->coll->add($value);

        $this->changed();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        $this->initialize();

        return $this->coll->isEmpty();
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator()
    {
        $this->initialize();

        return $this->coll->getIterator();
    }

    /**
     * {@inheritDoc}
     */
    public function map(Closure $func)
    {
        $this->initialize();

        return $this->coll->map($func);
    }

    /**
     * {@inheritDoc}
     */
    public function filter(Closure $p)
    {
        $this->initialize();

        return $this->coll->filter($p);
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(Closure $p)
    {
        $this->initialize();

        return $this->coll->forAll($p);
    }

    /**
     * {@inheritDoc}
     */
    public function partition(Closure $p)
    {
        $this->initialize();

        return $this->coll->partition($p);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        $this->initialize();

        return $this->coll->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        if ($this->initialized && $this->isEmpty()) {
            return;
        }

        $this->coll->clear();

        $this->initialized = true; // direct call, {@link initialize()} is too expensive

        if ($this->association['isOwningSide'] && $this->owner) {
            $this->changed();
            $this->takeSnapshot();
        }
    }

    /**
     * Called by PHP when this collection is serialized. Ensures that only the elements are properly serialized.
     *
     * @internal Tried to implement Serializable first but that did not work well
     *           with circular references. This solution seems simpler and works well.
     */
    public function __sleep()
    {
        return array('coll', 'initialized');
    }

    /* ArrayAccess implementation */

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return $this->containsKey($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        if (!isset($offset)) {
            return $this->add($value);
        }

        return $this->set($offset, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->coll->key();
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->coll->current();
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        return $this->coll->next();
    }

    /**
     * {@inheritDoc}
     */
    public function unwrap()
    {
        return $this->coll;
    }

    /**
     * {@inheritDoc}
     *
     * @todo Implement FETCH_EXTRA_LAZY slice
     */
    public function slice($offset, $length = null)
    {
        $this->initialize();

        return $this->coll->slice($offset, $length);
    }

    /**
     * Cleanup internal state of cloned persistent collection.
     *
     * The following problems have to be prevented:
     * 1. Added entities are added to old PC
     * 2. New collection is not dirty, if reused on other entity nothing changes.
     * 3. Snapshot leads to invalid diffs being generated.
     * 4. Lazy loading grabs entities from old owner object.
     * 5. New collection is connected to old owner and leads to duplicate keys.
     */
    public function __clone()
    {
        if (is_object($this->coll)) {
            $this->coll = clone $this->coll;
        }

        $this->initialize();

        $this->owner = null;
        $this->snapshot = array();

        $this->changed();
    }

    /**
     * {@inheritDoc}
     *
     * @todo Implement this function without the need to initialize the collection
     */
    public function matching(Criteria $criteria)
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->coll->matching($criteria);
    }
}
