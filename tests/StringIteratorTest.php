<?php

declare(strict_types=1);

use Componenta\Stdlib\StringIterator;

it('iterates UTF-8 strings by characters rather than bytes', function (): void {
    $iterator = new StringIterator('Aβ🙂');

    expect(iterator_to_array($iterator, false))->toBe(['A', 'β', '🙂'])
        ->and(count($iterator))->toBe(3)
        ->and((string) $iterator)->toBe('Aβ🙂');
});

it('moves to explicit character positions and rejects out-of-range positions', function (): void {
    $iterator = new StringIterator('abcd');

    expect($iterator->moveTo(2))->toBe($iterator)
        ->and($iterator->current())->toBe('c')
        ->and($iterator->key())->toBe(2);

    expect(fn() => $iterator->moveTo(-1))->toThrow(OutOfRangeException::class)
        ->and(fn() => $iterator->moveTo(4))->toThrow(OutOfRangeException::class);
});

it('moves forward and backward without crossing character boundaries', function (): void {
    $iterator = new StringIterator('abcd');

    $iterator->forward(10);
    expect($iterator->current())->toBe('d')
        ->and($iterator->isEnd())->toBeFalse();

    $iterator->backward(2);
    expect($iterator->current())->toBe('b')
        ->and($iterator->isStart())->toBeFalse();

    $iterator->backward(10);
    expect($iterator->current())->toBe('a')
        ->and($iterator->isStart())->toBeTrue();
});

it('reads and peeks without moving the cursor', function (): void {
    $iterator = new StringIterator('Aβ🙂Z');
    $iterator->moveTo(1);

    expect($iterator->read(2))->toBe('β🙂')
        ->and($iterator->remaining())->toBe('β🙂Z')
        ->and($iterator->peek())->toBe('🙂')
        ->and($iterator->peek(2))->toBe('Z')
        ->and($iterator->peek(-1))->toBe('A')
        ->and($iterator->peek(3))->toBeNull()
        ->and($iterator->current())->toBe('β')
        ->and($iterator->key())->toBe(1);
});

it('reports the past-the-end iterator state after normal iteration', function (): void {
    $iterator = new StringIterator('ab');

    $iterator->next();
    $iterator->next();

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->isEnd())->toBeTrue()
        ->and($iterator->current())->toBeNull()
        ->and($iterator->read())->toBe('')
        ->and($iterator->peek())->toBeNull();

    $iterator->rewind();

    expect($iterator->current())->toBe('a')
        ->and($iterator->isStart())->toBeTrue();
});

it('handles an empty string consistently', function (): void {
    $iterator = new StringIterator('');

    expect(count($iterator))->toBe(0)
        ->and($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull()
        ->and($iterator->read())->toBe('')
        ->and($iterator->remaining())->toBe('')
        ->and($iterator->peek())->toBeNull();
});

it('creates an independent iterator when replacing the string', function (): void {
    $iterator = new StringIterator('abc');
    $iterator->moveTo(1);

    $changed = $iterator->withString('xy');

    expect($changed)->not->toBe($iterator)
        ->and((string) $iterator)->toBe('abc')
        ->and($iterator->current())->toBe('b')
        ->and((string) $changed)->toBe('xy')
        ->and($changed->current())->toBe('x')
        ->and($changed->getEncoding())->toBe($iterator->getEncoding());
});
