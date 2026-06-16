<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Iterator;
use Countable;
use Componenta\Arrayable\Arrayable;
use Generator;
use IteratorAggregate;

/**
 * Iterator wrapper that caches traversed elements for replay.
 *
 * Enables multiple iterations over single-use sources like generators
 * by memoizing elements on first traversal. Subsequent iterations
 * read from cache without touching the original source.
 *
 * Key features:
 * - Lazy traversal with on-demand caching
 * - Preserves original keys (including null and duplicates)
 * - Safe for generators (no rewind attempts)
 * - Memory-efficient for partial iterations
 */
final class ReplayableIterator implements Iterator, Countable, Arrayable
{
    /**
     * Cache of traversed items as [key, value] pairs.
     * Using positional storage to correctly handle duplicate keys.
     *
     * @var list<array{mixed, mixed}>
     */
    private array $cache = [];

    /** @var int Current cache size (avoids repeated count() calls). */
    private(set) int $cacheSize = 0;

    /** @var bool Whether the iterable has been fully traversed. */
    private(set) bool $traversed = false;

    /** @var int Current position in iteration (0-indexed). */
    private(set) int $currentPosition = 0;

    /** @var Iterator|null Underlying iterator for lazy traversal. */
    private ?Iterator $iterable = null;

    /**
     * @param iterable<mixed, mixed> $iterable The iterable to wrap.
     */
    public function __construct(iterable $iterable)
    {
        is_array($iterable) ? $this->initializeFromArray($iterable)
            : $this->initializeFromIterator($iterable);
    }

    /**
     * Returns the total number of items.
     *
     * Forces full traversal if not already done.
     */
    public function count(): int
    {
        $this->traverseFully();

        return $this->cacheSize;
    }

    /**
     * Returns the current element.
     */
    public function current(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        $this->cacheCurrentIfNeeded();

        return $this->cache[$this->currentPosition][1];
    }

    /**
     * Returns the key of the current element.
     */
    public function key(): mixed
    {
        if (!$this->valid()) {
            return null;
        }

        $this->cacheCurrentIfNeeded();

        return $this->cache[$this->currentPosition][0];
    }

    /**
     * Moves forward to the next element.
     */
    public function next(): void
    {
        // Ensure current element is cached before advancing position
        if ($this->valid()) {
            $this->cacheCurrentIfNeeded();
        }

        $this->currentPosition++;
    }

    /**
     * Checks whether the current position is valid.
     */
    public function valid(): bool
    {
        if ($this->traversed) {
            return $this->currentPosition < $this->cacheSize;
        }

        // Position is within cache
        if ($this->currentPosition < $this->cacheSize) {
            return true;
        }

        // Check underlying iterator
        $isValid = $this->iterable?->valid() ?? false;

        if (!$isValid) {
            $this->markAsTraversed();
        }

        return $isValid;
    }

    /**
     * Rewinds the iterator to the first element.
     */
    public function rewind(): void
    {
        $this->currentPosition = 0;

        // Generators cannot be rewound; for other iterators, rewind only if
        // we haven't cached anything yet (to avoid inconsistency)
        if (!$this->traversed && $this->cacheSize === 0 && !$this->iterable instanceof Generator) {
            $this->iterable->rewind();
        }
    }

    /**
     * Forces full traversal and returns all items as an array.
     *
     * @param bool $preserveKeys Whether to preserve original keys.
     *                           Note: with duplicate keys, later values overwrite earlier ones.
     * @return array<mixed, mixed>|list<mixed>
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

    /**
     * Initializes state from an array source.
     *
     * @param array<mixed, mixed> $array
     */
    private function initializeFromArray(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->cache[] = [$key, $value];
        }
        $this->cacheSize = count($this->cache);
        $this->traversed = true;
    }

    /**
     * Initializes state from an iterator source.
     *
     * @param Iterator|IteratorAggregate $iterable
     */
    private function initializeFromIterator(Iterator|IteratorAggregate $iterable): void
    {
        $iterator = $this->unwrapIterator($iterable);

        $this->iterable = $iterator;

        // Ensure iterator is at the beginning (except for generators)
        if (!$iterator instanceof Generator) {
            $iterator->rewind();
        }
    }

    /**
     * Recursively unwraps IteratorAggregate to get the underlying Iterator.
     */
    private function unwrapIterator(Iterator|IteratorAggregate $iterable): Iterator
    {
        while ($iterable instanceof IteratorAggregate) {
            $iterable = $iterable->getIterator();
        }

        return $iterable;
    }

    /**
     * Caches the current element from the underlying iterator if not already cached.
     * After caching, advances the underlying iterator to prevent double-caching.
     */
    private function cacheCurrentIfNeeded(): void
    {
        // Already cached or fully traversed
        if ($this->traversed || $this->currentPosition < $this->cacheSize) {
            return;
        }

        // Underlying iterator must be valid at this point
        if (!$this->iterable->valid()) {
            return;
        }

        $this->cache[] = [
            $this->iterable->key(),
            $this->iterable->current(),
        ];
        $this->cacheSize++;

        // Advance immediately to prevent caching same element twice
        $this->iterable->next();
    }

    /**
     * Forces complete traversal of the underlying iterator.
     */
    private function traverseFully(): void
    {
        if ($this->traversed || $this->iterable === null) {
            return;
        }

        // Simply cache all remaining elements from the iterator
        while ($this->iterable->valid()) {
            $this->cache[] = [
                $this->iterable->key(),
                $this->iterable->current(),
            ];
            $this->cacheSize++;
            $this->iterable->next();
        }

        $this->markAsTraversed();
    }

    /**
     * Marks the iterator as fully traversed and releases the source iterator.
     */
    private function markAsTraversed(): void
    {
        $this->traversed = true;
        $this->iterable = null;
    }
}
