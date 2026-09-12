<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('exposes replay progress as read-only derived state', function (): void {
    $source = (static function (): Generator {
        yield 1;
        yield 2;
        yield 3;
    })();
    $iterator = new ReplayableIterator($source);

    expect($iterator->cacheSize)->toBe(0)
        ->and($iterator->traversed)->toBeFalse()
        ->and($iterator->currentPosition)->toBe(0)
        ->and($iterator->current())->toBe(1)
        ->and($iterator->cacheSize)->toBe(1)
        ->and($iterator->traversed)->toBeFalse();

    $iterator->next();

    expect($iterator->currentPosition)->toBe(1)
        ->and(iterator_to_array($iterator->cursor(), false))->toBe([1, 2, 3])
        ->and($iterator->cacheSize)->toBe(3)
        ->and($iterator->traversed)->toBeTrue();
});

it('reports arrays as fully traversed immediately', function (): void {
    $iterator = new ReplayableIterator(['a', 'b']);

    expect($iterator->cacheSize)->toBe(2)
        ->and($iterator->traversed)->toBeTrue()
        ->and($iterator->currentPosition)->toBe(0);
});

it('finishes a lazy source correctly after exactly one cached item', function (): void {
    $iterator = new ReplayableIterator((static function (): Generator {
        yield 1;
        yield 2;
        yield 3;
    })());

    expect($iterator->current())->toBe(1)
        ->and($iterator->cacheSize)->toBe(1)
        ->and($iterator->toArray())->toBe([1, 2, 3])
        ->and($iterator->current())->toBe(1)
        ->and($iterator->traversed)->toBeTrue();
});
