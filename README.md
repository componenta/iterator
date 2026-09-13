# Componenta Iterator

Iterator utilities for replayable iteration, reverse traversal, string traversal, and array conversion.

Use this package when a library needs iterator behavior without depending on collection frameworks.

## Installation

```bash
composer require componenta/iterator
```

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/stream-iterator` | Prefer it for large PSR-7 streams because it keeps only the current chunk in memory. |
| `componenta/arrayable` | Some iterators expose `toArray()` for array conversion. |

## ReplayableIterator

`ReplayableIterator` wraps arrays, iterators, iterator aggregates, and generators. It caches traversed entries lazily so a one-shot source can be replayed without reading the whole source up front.

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
If the source throws, subsequent reads and advancing existing cursors rethrow that
same exception; a failed source cannot become a successful truncated result.

Every cursor has a local position and shares only the lazy replay cache. Duplicate and `null` source keys are retained internally. Calling `count()` or `toArray()` forces full traversal of the wrapped source without moving the manual cursor.

## Reverse Iteration

`ReverseIterator` iterates an iterable in reverse order. It materializes values internally, so it is intended for finite iterables.

`ArrayListReverseIterator` is a small reverse iterator for list arrays.

## StringIterator

`StringIterator` requires the PHP `mbstring` extension and iterates over a string with encoding support and cursor helpers:

- `moveTo()`
- `forward()`
- `backward()`
- `read()`
- `remaining()`
- `peek()`

## Array Conversion

`IteratorToArray` exposes a `toArray()` contract for iterator classes that can materialize their contents.

## Development

```bash
composer install
composer test
```

CI validates Composer metadata, lints PHP files, and runs Pest on PHP 8.4 and 8.5.

## Memory Notes

Replayable and reverse iterators trade memory for traversal behavior. They are appropriate for finite sequences. For large PSR-7 streams, use `componenta/stream-iterator`, which keeps only the current chunk.


`StringIterator::forward()` accepts non-negative steps and can advance to the end
position, where `isEnd()` is true and `current()` is null. A zero step preserves
the position; a step beyond the remaining length stops at the end. Calling
`backward()` from the end returns to the final character. Negative movement steps
throw `InvalidArgumentException`.
