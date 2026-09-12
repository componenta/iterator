<?php

declare(strict_types=1);

use Componenta\Stdlib\ArrayListReverseIterator;

it('iterates list values and original indexes in reverse order', function (): void {
    $iterator = new ArrayListReverseIterator(['a', 'b', 'c']);

    expect(iterator_to_array($iterator, true))->toBe([
        2 => 'c',
        1 => 'b',
        0 => 'a',
    ]);
});

it('can be rewound and traversed again', function (): void {
    $iterator = new ArrayListReverseIterator([1, 2, 3]);

    $first = iterator_to_array($iterator, false);
    $second = iterator_to_array($iterator, false);

    expect($first)->toBe([3, 2, 1])
        ->and($second)->toBe($first);
});

it('handles empty and single-element lists', function (): void {
    expect(iterator_to_array(new ArrayListReverseIterator([]), false))->toBe([])
        ->and(iterator_to_array(new ArrayListReverseIterator(['only']), true))->toBe([0 => 'only']);
});

it('exposes standard Iterator state while traversing backward', function (): void {
    $iterator = new ArrayListReverseIterator(['a', 'b']);

    expect($iterator->valid())->toBeTrue()
        ->and($iterator->key())->toBe(1)
        ->and($iterator->current())->toBe('b');

    $iterator->next();

    expect($iterator->valid())->toBeTrue()
        ->and($iterator->key())->toBe(0)
        ->and($iterator->current())->toBe('a');

    $iterator->next();

    expect($iterator->valid())->toBeFalse();
});

it('returns null from current after traversal has finished', function (): void {
    $iterator = new ArrayListReverseIterator(['only']);
    $iterator->next();

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull();
});
