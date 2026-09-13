<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use Componenta\Arrayable\Arrayable;

/**
 * Iterator that traverses an iterable in reverse order.
 *
 * @implements \Iterator<mixed, mixed>
 */
final class ReverseIterator implements \Iterator, Arrayable
{
    use IteratorToArray;

    /** @var list<array{mixed, mixed}> */
    private array $entries = [];

    /** @var iterable<mixed, mixed>|null Original iterable (consumed on first rewind). */
    private ?iterable $iterable;

    private int $position = -1;

    /**
     * @param iterable<mixed, mixed> $iterable The iterable to reverse.
     */
    public function __construct(iterable $iterable)
    {
        $this->iterable = $iterable;
    }

    /**
     * Creates a new instance with a different iterable.
     *
     * @param iterable<mixed, mixed> $iterable The new iterable.
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
        return $this->valid()
            ? $this->entries[$this->position][1]
            : null;
    }

    /**
     * Moves to the previous element (reverse iteration).
     */
    public function next(): void
    {
        --$this->position;
    }

    /**
     * Returns the key of the current element.
     */
    public function key(): mixed
    {
        return $this->valid()
            ? $this->entries[$this->position][0]
            : null;
    }

    /**
     * Checks if the current position is valid.
     */
    public function valid(): bool
    {
        return $this->position >= 0;
    }

    /**
     * Rewinds to the last element (start of reverse iteration).
     */
    public function rewind(): void
    {
        if ($this->iterable !== null) {
            $entries = [];
            foreach ($this->iterable as $key => $value) {
                $entries[] = [$key, $value];
            }

            $this->entries = $entries;
            $this->iterable = null;
        }

        $this->position = count($this->entries) - 1;
    }
}
