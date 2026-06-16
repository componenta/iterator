<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

use OutOfRangeException;

/**
 * Iterator for traversing a string character by character.
 *
 * Supports multibyte strings (UTF-8).
 */
final class StringIterator implements \Iterator, \Stringable, \Countable
{
    /** @var int Length of the string in characters. */
    private(set) int $length;

    /** @var int Current position in the string. */
    private(set) int $position = 0;

    /**
     * @param string $string The string to iterate over.
     * @param string $encoding Character encoding (default: UTF-8).
     */
    public function __construct(
        private(set) readonly string $string,
        private readonly string $encoding = 'UTF-8'
    ) {
        $this->length = mb_strlen($string, $this->encoding);
    }

    /**
     * Returns the string.
     */
    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Returns the character encoding.
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Returns the number of characters.
     */
    public function count(): int
    {
        return $this->length;
    }

    /**
     * Creates a new instance with a different string.
     *
     * @param string $string The new string.
     * @return self
     */
    public function withString(string $string): self
    {
        return new self($string, $this->encoding);
    }

    /**
     * Returns the current character.
     *
     * @return string|null Current character or null if position is invalid.
     */
    public function current(): ?string
    {
        if (!$this->valid()) {
            return null;
        }

        // Use mb_substr for proper multibyte support
        return mb_substr($this->string, $this->position, 1, $this->encoding);
    }

    /**
     * Moves to the next character.
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Returns the current position.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Checks if the current position is valid.
     */
    public function valid(): bool
    {
        return $this->position >= 0 && $this->position < $this->length;
    }

    /**
     * Resets to the beginning.
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if at the end of the string.
     */
    public function isEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Checks if at the beginning of the string.
     */
    public function isStart(): bool
    {
        return $this->position <= 0;
    }

    /**
     * Moves to a specific position.
     *
     * @param int $position The new position (0-indexed).
     * @return $this
     * @throws OutOfRangeException If position is out of bounds.
     */
    public function moveTo(int $position): self
    {
        if ($position < 0 || $position >= $this->length) {
            throw new OutOfRangeException(
                sprintf('Position %d is out of range [0, %d)', $position, $this->length)
            );
        }

        $this->position = $position;
        return $this;
    }

    /**
     * Moves forward by a number of characters.
     *
     * Stops at the end of the string.
     *
     * @param int $steps Number of characters to move forward.
     * @return $this
     */
    public function forward(int $steps = 1): self
    {
        $this->position = min($this->position + $steps, $this->length - 1);

        // Handle empty string edge case
        if ($this->length === 0) {
            $this->position = 0;
        }

        return $this;
    }

    /**
     * Moves backward by a number of characters.
     *
     * Stops at the beginning of the string.
     *
     * @param int $steps Number of characters to move backward.
     * @return $this
     */
    public function backward(int $steps = 1): self
    {
        $this->position = max($this->position - $steps, 0);
        return $this;
    }

    /**
     * Reads characters from the current position.
     *
     * @param int|null $length Number of characters to read, or null for rest of string.
     * @return string The substring.
     */
    public function read(?int $length = null): string
    {
        if ($this->isEnd()) {
            return '';
        }

        return mb_substr($this->string, $this->position, $length, $this->encoding);
    }

    /**
     * Returns remaining characters from current position.
     *
     * @return string
     */
    public function remaining(): string
    {
        return $this->read();
    }

    /**
     * Peeks at the next character without moving position.
     *
     * @param int $offset Offset from current position (default: 1).
     * @return string|null The character or null if out of bounds.
     */
    public function peek(int $offset = 1): ?string
    {
        $peekPosition = $this->position + $offset;

        if ($peekPosition < 0 || $peekPosition >= $this->length) {
            return null;
        }

        return mb_substr($this->string, $peekPosition, 1, $this->encoding);
    }
}
