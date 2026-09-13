<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('keeps independent replay cursors and the manual cursor over one lazy source', function (): void {
    $reads = 0;
    $iterator = new ReplayableIterator((static function () use (&$reads): Generator {
        foreach ([['same', 'first'], ['same', 'second'], [null, 'third']] as [$key, $value]) {
            ++$reads;
            yield $key => $value;
        }
    })());
    $left = $iterator->replay();
    $right = $iterator->replay();
    expect($reads)->toBe(0);
    $left->rewind();
    $left->next();
    $right->rewind();
    $iterator->rewind();

    expect($left->current())->toBe('second')
        ->and($right->current())->toBe('first')
        ->and($iterator->current())->toBe('first');
    expect(count($iterator))->toBe(3);
    expect($left->current())->toBe('second')
        ->and($right->current())->toBe('first')
        ->and($iterator->current())->toBe('first');

    $pairs = [];
    foreach ($iterator->replay() as $key => $value) {
        $pairs[] = [$key, $value];
        expect($iterator->toArray())->toBe(['first', 'second', 'third']);
    }
    expect($pairs)->toBe([['same', 'first'], ['same', 'second'], [null, 'third']])
        ->and($reads)->toBe(3);
});

it('shares source failure with an already suspended replay', function (): void {
    $failure = new RuntimeException('failed later');
    $iterator = new ReplayableIterator((static function () use ($failure): Generator {
        yield 'first';
        yield 'second';
        throw $failure;
    })());
    $replay = $iterator->replay();
    $replay->rewind();
    expect($replay->current())->toBe('first');

    foreach ([static fn () => $iterator->toArray(), static fn () => $replay->next(), static fn () => iterator_to_array($iterator->replay())] as $read) {
        $caught = null;
        try {
            $read();
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
    }
});
