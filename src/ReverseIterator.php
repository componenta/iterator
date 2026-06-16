<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Componenta\Arrayable\Arrayable;

/**
 * Iterator that traverses an iterable in reverse order.
 */
final class ReverseIterator implements \Iterator, Arrayable
{
    use IteratorToArray;

    /** @var array Cached data for reverse iteration. */
    private array $data = [];

    /** @var iterable|null Original iterable (consumed on first rewind). */
    private ?iterable $iterable;

    /**
     * @param iterable $iterable The iterable to reverse.
     */
    public function __construct(iterable $iterable)
    {
        $this->iterable = $iterable;
    }

    /**
     * Creates a new instance with a different iterable.
     *
     * @param iterable $iterable The new iterable.
     * @return self
     */
    public function withIterable(iterable $iterable): self
    {
        return new self($iterable);
    }

    /**
     * Returns the current element.
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * Moves to the previous element (reverse iteration).
     */
    public function next(): void
    {
        prev($this->data);
    }

    /**
     * Returns the key of the current element.
     */
    public function key(): mixed
    {
        return key($this->data);
    }

    /**
     * Checks if the current position is valid.
     */
    public function valid(): bool
    {
        $key = key($this->data);
        return $key !== null;
    }

    /**
     * Rewinds to the last element (start of reverse iteration).
     */
    public function rewind(): void
    {
        if ($this->iterable !== null) {
            $this->data = to_array($this->iterable);
            $this->iterable = null;
        }

        end($this->data);
    }
}
