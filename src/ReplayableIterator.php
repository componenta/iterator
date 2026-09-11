<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Componenta\Arrayable\Arrayable;
use Countable;
use Generator;
use Iterator;
use IteratorAggregate;

/**
 * Iterator wrapper that caches traversed elements for replay.
 *
 * Direct Iterator methods retain their traditional single-cursor behavior.
 * Use cursor() whenever independent or interleaved traversal is required.
 * All cursors share the same lazy cache while keeping their positions local.
 */
final class ReplayableIterator implements Iterator, Countable, Arrayable
{
    /** @var list<array{mixed, mixed}> */
    private array $cache = [];

    private(set) int $cacheSize = 0;
    private(set) bool $traversed = false;
    private(set) int $currentPosition = 0;

    private ?Iterator $iterable = null;

    /** @param iterable<mixed, mixed> $iterable */
    public function __construct(iterable $iterable)
    {
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
     * @return Generator<mixed, mixed>
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

    public function count(): int
    {
        $this->traverseFully();

        return $this->cacheSize;
    }

    public function current(): mixed
    {
        if (!$this->ensureCached($this->currentPosition)) {
            return null;
        }

        return $this->cache[$this->currentPosition][1];
    }

    public function key(): mixed
    {
        if (!$this->ensureCached($this->currentPosition)) {
            return null;
        }

        return $this->cache[$this->currentPosition][0];
    }

    public function next(): void
    {
        $this->currentPosition++;
    }

    public function valid(): bool
    {
        return $this->ensureCached($this->currentPosition);
    }

    public function rewind(): void
    {
        $this->currentPosition = 0;
    }

    /**
     * @param bool $preserveKeys With duplicate keys, later values overwrite earlier ones.
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

    /** @param array<mixed, mixed> $array */
    private function initializeFromArray(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->cache[] = [$key, $value];
        }

        $this->cacheSize = count($this->cache);
        $this->traversed = true;
    }

    /** @param Iterator|IteratorAggregate $iterable */
    private function initializeFromIterator(Iterator|IteratorAggregate $iterable): void
    {
        $iterator = $this->unwrapIterator($iterable);
        $this->iterable = $iterator;

        if (!$iterator instanceof Generator) {
            $iterator->rewind();
        }
    }

    private function unwrapIterator(Iterator|IteratorAggregate $iterable): Iterator
    {
        $seen = new \SplObjectStorage();

        while ($iterable instanceof IteratorAggregate) {
            if ($seen->contains($iterable)) {
                throw new \RuntimeException('IteratorAggregate cycle detected');
            }

            $seen->attach($iterable);
            $iterable = $iterable->getIterator();
        }

        return $iterable;
    }

    /**
     * Ensures that an entry at the requested cache position exists if the
     * underlying iterable can still produce it.
     */
    private function ensureCached(int $position): bool
    {
        if ($position < $this->cacheSize) {
            return true;
        }

        if ($this->iterable === null) {
            return false;
        }

        while ($position >= $this->cacheSize) {
            if (!$this->iterable->valid()) {
                $this->markAsTraversed();

                return false;
            }

            $this->cache[] = [
                $this->iterable->key(),
                $this->iterable->current(),
            ];
            $this->cacheSize++;
            $this->iterable->next();
        }

        return true;
    }

    private function traverseFully(): void
    {
        if ($this->iterable === null) {
            return;
        }

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

    private function markAsTraversed(): void
    {
        $this->traversed = true;
        $this->iterable = null;
    }
}
