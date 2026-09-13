<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('can stop at the first value without executing the rest of the source', function (bool $replay): void {
    $source = (static function (): Generator {
        yield 'first';
        throw new RuntimeException('Unrequested source continuation');
    })();
    $iterator = new ReplayableIterator($source);
    $values = [];

    foreach ($replay ? $iterator->replay() : $iterator as $value) {
        $values[] = $value;
        break;
    }

    expect($values)->toBe(['first']);
})->with(['manual iterator' => false, 'independent replay' => true]);

it('delivers every yielded value before propagating the original source failure', function (bool $replay): void {
    $failure = new RuntimeException('Source failed after its last value');
    $iterator = new ReplayableIterator((static function () use ($failure): Generator {
        yield 'same' => 'first';
        yield 'same' => 'second';
        throw $failure;
    })());
    $values = [];
    $caught = null;

    try {
        foreach ($replay ? $iterator->replay() : $iterator as $key => $value) {
            $values[] = [$key, $value];
        }
    } catch (RuntimeException $error) {
        $caught = $error;
    }

    expect($values)->toBe([['same', 'first'], ['same', 'second']])
        ->and($caught)->toBe($failure);
})->with(['manual iterator' => false, 'independent replay' => true]);

it('advances the shared source only when a cursor requests a new position', function (): void {
    $events = [];
    $iterator = new ReplayableIterator((static function () use (&$events): Generator {
        $events[] = 'first';
        yield 'a' => 'first';
        $events[] = 'second';
        yield 'b' => 'second';
        $events[] = 'finished';
    })());
    $replay = $iterator->replay();

    $iterator->rewind();
    expect($iterator->current())->toBe('first')
        ->and($iterator->key())->toBe('a')
        ->and($iterator->current())->toBe('first')
        ->and($events)->toBe(['first']);

    $replay->rewind();
    expect($replay->current())->toBe('first')
        ->and($events)->toBe(['first']);

    $iterator->next();
    expect($events)->toBe(['first', 'second'])
        ->and($iterator->current())->toBe('second');

    $replay->next();
    expect($replay->current())->toBe('second')
        ->and($events)->toBe(['first', 'second']);

    expect(count($iterator))->toBe(2)
        ->and($events)->toBe(['first', 'second', 'finished'])
        ->and($iterator->current())->toBe('second')
        ->and($iterator->toArray(true))->toBe(['a' => 'first', 'b' => 'second']);
});
