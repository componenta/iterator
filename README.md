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

`ReplayableIterator` wraps arrays, iterators, iterator aggregates, and generators. It caches traversed values so a one-shot source can be iterated again.

```php
use Componenta\Stdlib\ReplayableIterator;

$iterator = new ReplayableIterator((function () {
    yield 'a' => 1;
    yield 'b' => 2;
})());

$iterator->toArray(preserveKeys: true); // ['a' => 1, 'b' => 2]
```

Calling `count()` or `toArray()` forces full traversal of the wrapped source.

## Reverse Iteration

`ReverseIterator` iterates an iterable in reverse order. It materializes values internally, so it is intended for finite iterables.

`ArrayListReverseIterator` is a small reverse iterator for list arrays.

## StringIterator

`StringIterator` iterates over a string with encoding support and cursor helpers:

- `moveTo()`
- `forward()`
- `backward()`
- `read()`
- `remaining()`
- `peek()`

## Array Conversion

`IteratorToArray` exposes a `toArray()` contract for iterator classes that can materialize their contents.

## Memory Notes

Replayable and reverse iterators trade memory for traversal behavior. They are appropriate for finite sequences. For large PSR-7 streams, use `componenta/stream-iterator`, which keeps only the current chunk.
