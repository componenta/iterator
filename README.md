# Componenta Iterator

[Русская версия](README.ru.md)

Iterator utilities for replayable iteration, reverse traversal, string traversal, and array conversion.

Use this package when a library needs iterator behavior without depending on collection frameworks.

## Installation

```bash
composer require componenta/iterator
```

## Requirements

- PHP 8.4+
- `ext-mbstring`
- `componenta/arrayable` 1.x

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/stream-iterator` | Prefer it for large PSR-7 streams because it keeps only the current chunk in memory. |
| `componenta/arrayable` | Some iterators expose `toArray()` for array conversion. |

## ReplayableIterator

`ReplayableIterator` wraps arrays, iterators, iterator aggregates, and generators. It caches traversed entries lazily so a one-shot source can be replayed without reading the whole source up front. The wrapper owns traversal of the supplied iterator; callers must not advance it externally. A generator is consumed from its current position.

```php
use Componenta\Stdlib\ReplayableIterator;

$iterator = new ReplayableIterator((function () {
    yield 'a' => 1;
    yield 'b' => 2;
})());

$iterator->toArray(preserveKeys: true); // ['a' => 1, 'b' => 2]
```

The object still implements the traditional single-cursor `Iterator` API. When two consumers must traverse the same source independently or concurrently, create independent cursors:

```php
$first = $iterator->cursor();
$second = $iterator->cursor();

$first->next();
// $second still points at its own first entry.
```

`replay()` is an alias for `cursor()` and returns a new independent cursor.
Cloning an active wrapper is rejected: a shallow clone would share its source
but have a separate cache. Use independent cursors instead. A fully traversed
wrapper can be cloned with independent manual positions. When converting to an
array with preserved keys, null keys are represented as empty strings; duplicate
array keys use the last value.
If the source throws, subsequent reads and advancing existing cursors rethrow that
same exception; a failed source cannot become a successful truncated result.
Destroying a Fiber during an active source read invalidates the replay with a
`LogicException` rather than treating the interrupted traversal as successful.

If a source operation is still in progress, another read that needs an uncached
entry throws `LogicException`. Already cached entries remain readable. Rejecting
an overlapping read leaves the original reader and cache intact, and a rejected
manual `next()` does not move its cursor. Once the source operation finishes,
new cursors and manual reads can continue normally.

Every cursor has a local position and shares only the lazy replay cache. Duplicate
and `null` source keys are retained internally. `toArray()` returns a list by
default; `toArray(true)` preserves PHP-compatible array keys, with the last value
winning on duplicate keys. Calling `count()` or `toArray()` forces full traversal
without moving the manual cursor. `cacheSize`, `traversed` and `currentPosition`
are read-only diagnostics; a failed or interrupted traversal is not `traversed`.

Array input is cached immediately. A non-generator iterator is rewound once when
wrapped; a generator is read from its current position without rewinding.

## Reverse Iteration

`ReverseIterator` iterates an iterable in reverse order. It materializes values internally, so it is intended for finite iterables.

`ReverseIterator` preserves original keys during traversal. It reads the source on
first rewind; `withIterable()` creates a new wrapper for a different source.
`toArray()` follows `iterator_to_array()` key semantics.

`ArrayListReverseIterator` is a reverse iterator for list arrays (contiguous integer
keys starting at zero); it preserves the original indexes.

## StringIterator

`StringIterator` requires the PHP `mbstring` extension and iterates over a string with encoding support and cursor helpers:

- `moveTo()`
- `forward()`
- `backward()`
- `read()`
- `remaining()`
- `peek()`

`moveTo()` selects an existing zero-based character index and rejects out-of-range
positions. `read()`, `remaining()` and `peek()` do not move the cursor.
`count()` returns the character count, `getEncoding()` returns the encoding and
`withString()` creates a new iterator with the same encoding. `string`, `length`
and `position` are read-only from outside; casting to string returns the source.

`StringIterator::forward()` accepts non-negative steps and can advance to the end
position, where `isEnd()` is true and `current()` is null. A zero step preserves
the position; a step beyond the remaining length stops at the end. Calling
`backward()` from the end returns to the final character. Negative movement steps
throw `InvalidArgumentException`.

## Array Conversion

`IteratorToArray` is a trait implementing `toArray()` through `iterator_to_array()`.
The public array-conversion interface is `Componenta\Arrayable\Arrayable`.
`ReplayableIterator` provides its own key-preserving or list conversion.

## Development

```bash
composer install
composer test
```

CI validates Composer metadata, lints PHP files, and runs Pest on PHP 8.4 and 8.5.

## Memory Notes

Replayable and reverse iterators trade memory for traversal behavior. They are appropriate for finite sequences. For large PSR-7 streams, use `componenta/stream-iterator`, which keeps only the current chunk.
