<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Componenta\Arrayable\Arrayable;
use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use IteratorIterator;
use Throwable;
use Traversable;

/**
 * Iterator wrapper that caches traversed elements for replay.
 *
 * Direct Iterator methods retain their traditional single-cursor behavior.
 * Use cursor() whenever independent or interleaved traversal is required.
 * All cursors share the same lazy cache while keeping their positions local.
 *
 * @template TKey
 * @template TValue
 * @implements Iterator<TKey, TValue>
 */
final class ReplayableIterator implements Iterator, Countable, Arrayable
{
    /** @var list<array{TKey, TValue}> */
    private array $cache = [];

    /** @var non-negative-int */
    public int $cacheSize {
        get => count($this->cache);
    }

    public bool $traversed {
        get => $this->iterable === null && $this->failure === null;
    }

    private(set) int $currentPosition;

    /** @var Iterator<TKey, TValue>|null */
    private ?Iterator $iterable = null;
    private ?Throwable $failure = null;

    /** @param iterable<TKey, TValue> $iterable */
    public function __construct(iterable $iterable)
    {
        $this->currentPosition = 0;

        is_array($iterable)
            ? $this->initializeFromArray($iterable)
            : $this->initializeFromIterator($iterable);
    }

    /**
     * Returns an independent lazy cursor over the replayable sequence.
     *
     * Multiple cursors may be consumed in any interleaving order. They share
     * cached source entries but never share a cursor position.
     *
     * @return Generator<TKey, TValue>
     */
    public function cursor(): Generator
    {
        $position = 0;

        while ($this->ensureCached($position)) {
            [$key, $value] = $this->cache[$position];
            yield $key => $value;
            $position++;
        }
    }

    /** @return Generator<TKey, TValue> */
    public function replay(): Generator
    {
        return $this->cursor();
    }

    public function count(): int
    {
        $this->traverseFully();

        return $this->cacheSize;
    }

    /** @return TValue|null */
    public function current(): mixed
    {
        if (!$this->ensureCached($this->currentPosition)) {
            return null;
        }

        return $this->cache[$this->currentPosition][1];
    }

    /** @return TKey|null */
    public function key(): mixed
    {
        if (!$this->ensureCached($this->currentPosition)) {
            return null;
        }

        return $this->cache[$this->currentPosition][0];
    }

    public function next(): void
    {
        $this->assertHealthy();
        $this->currentPosition++;
        $this->ensureCached($this->currentPosition);
    }

    public function valid(): bool
    {
        return $this->ensureCached($this->currentPosition);
    }

    public function rewind(): void
    {
        $this->assertHealthy();
        $this->currentPosition = 0;
    }

    /**
     * @param bool $preserveKeys With duplicate keys, later values overwrite earlier ones.
     * @return array<array-key, TValue>
     */
    public function toArray(bool $preserveKeys = false): array
    {
        $this->traverseFully();

        if (!$preserveKeys) {
            return array_column($this->cache, 1);
        }

        $result = [];

        foreach ($this->cache as [$key, $value]) {
            $result[$key] = $value;
        }

        return $result;
    }

    /** @param array<TKey, TValue> $array */
    private function initializeFromArray(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->cache[] = [$key, $value];
        }
    }

    /** @param Traversable<TKey, TValue> $iterable */
    private function initializeFromIterator(Traversable $iterable): void
    {
        $iterator = $this->unwrapIterator($iterable);
        $this->iterable = $iterator;

        if (!$iterator instanceof Generator) {
            $iterator->rewind();
        }
    }

    /**
     * @param Traversable<TKey, TValue> $iterable
     * @return Iterator<TKey, TValue>
     */
    private function unwrapIterator(Traversable $iterable): Iterator
    {
        $seen = new \SplObjectStorage();

        while ($iterable instanceof IteratorAggregate) {
            if ($seen->offsetExists($iterable)) {
                throw new \RuntimeException('IteratorAggregate cycle detected');
            }

            $seen->offsetSet($iterable, null);
            $iterable = $iterable->getIterator();
        }

        return $iterable instanceof Iterator ? $iterable : new IteratorIterator($iterable);
    }

    /**
     * Ensures that an entry at the requested cache position exists if the
     * underlying iterable can still produce it.
     */
    private function ensureCached(int $position): bool
    {
        $this->assertHealthy();

        if ($position < $this->cacheSize) {
            return true;
        }

        if ($this->iterable === null) {
            return false;
        }

        try {
            while ($position >= $this->cacheSize) {
                if ($this->cacheSize > 0) {
                    $this->iterable->next();
                }

                if (!$this->iterable->valid()) {
                    $this->iterable = null;
                    return false;
                }

                $this->cache[] = [
                    $this->iterable->key(),
                    $this->iterable->current(),
                ];
            }
        } catch (Throwable $failure) {
            $this->failure = $failure;
            $this->iterable = null;
            throw $failure;
        }

        return true;
    }

    private function traverseFully(): void
    {
        while ($this->ensureCached($this->cacheSize)) {
        }
    }

    private function assertHealthy(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
