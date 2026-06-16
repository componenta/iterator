<?php

declare(strict_types=1);

namespace Componenta\Stdlib;

/**
 * Trait for converting an iterator to an array.
 */
trait IteratorToArray
{
    /**
     * Converts the iterator to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }
}
