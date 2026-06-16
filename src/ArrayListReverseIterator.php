<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Iterator;

/**
 * @template T
 * @implements Iterator<int, T>
 */
final class ArrayListReverseIterator implements Iterator
{
    private int $lastIndex;

    private int $currentIndex;

    /**
     * @param list<T> $items
     */
    public function __construct(
        private readonly array $items
    ) {
        $this->lastIndex = count($items) - 1;
        $this->currentIndex = $this->lastIndex;
    }

    public function current(): mixed
    {
        return $this->items[$this->currentIndex];
    }

    public function next(): void
    {
        --$this->currentIndex;
    }

    public function key(): int
    {
        return $this->currentIndex;
    }

    public function valid(): bool
    {
        return $this->currentIndex >= 0;
    }

    public function rewind(): void
    {
        $this->currentIndex = $this->lastIndex;
    }
}
