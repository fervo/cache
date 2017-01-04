<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Bridge\SimpleCache;

use Cache\Bridge\SimpleCache\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

class SimpleCacheBridge implements CacheInterface
{
    /**
     * @type CacheItemPoolInterface
     */
    protected $cacheItemPool;

    /**
     * SimpleCacheBridge constructor.
     */
    public function __construct(CacheItemPoolInterface $cacheItemPool)
    {
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        try {
            $item = $this->cacheItemPool->getItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->cacheItemPool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->cacheItemPool->deleteItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->cacheItemPool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }

            // Since we need to throw an exception if *any* key is invalid, it doesn't
            // make sense to wrap iterators or something like that.
            $keys = iterator_to_array($keys);
        }

        try {
            $items = $this->cacheItemPool->getItems($keys);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($items as $key => $item) {
            /** @type $item CacheItemInterface */
            if (!$item->isHit()) {
                yield $key => $default;
            } else {
                yield $key => $item->get();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values)) {
            if (!$values instanceof \Traversable) {
                throw new InvalidArgumentException('$values is neither an array nor Traversable');
            }

            // Since we need to throw an exception if *any* key is invalid, it doesn't
            // make sense to wrap iterators or something like that.
            $values = iterator_to_array($values);
        }

        try {
            $items = $this->cacheItemPool->getItems(array_keys($values));
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $itemSuccess = true;

        foreach ($items as $key => $item) {
            /* @var $item CacheItemInterface */
            $item->set($values[$key]);
            $item->expiresAfter($ttl);

            $itemSuccess = $itemSuccess && $this->cacheItemPool->saveDeferred($item);
        }

        return $itemSuccess && $this->cacheItemPool->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }

            // Since we need to throw an exception if *any* key is invalid, it doesn't
            // make sense to wrap iterators or something like that.
            $keys = iterator_to_array($keys);
        }

        try {
            return $this->cacheItemPool->deleteItems($keys);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        try {
            return $this->cacheItemPool->hasItem($key);
        } catch (CacheInvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
