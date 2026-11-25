<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('rejects shallow cloning without damaging the active source', function (bool $started): void {
    $source = (static function (): Generator {
        yield 'first';
        yield 'second';
        yield 'third';
    })();
    $iterator = new ReplayableIterator($source);

    if ($started) {
        expect($iterator->current())->toBe('first');
    }

    expect(fn () => clone $iterator)->toThrow(LogicException::class, 'Cannot clone an active replayable iterator')
        ->and($iterator->toArray())->toBe(['first', 'second', 'third'])
        ->and(iterator_to_array($iterator->cursor(), false))->toBe(['first', 'second', 'third']);
})->with([false, true]);

it('materializes null keys consistently without losing the original cursor keys', function (): void {
    $iterator = new ReplayableIterator((static function (): Generator {
        yield null => 'null';
        yield '' => 'empty';
        yield 'named' => 'value';
    })());
    $pairs = [];
    foreach ($iterator->cursor() as $key => $value) {
        $pairs[] = [$key, $value];
    }

    expect($pairs)->toBe([[null, 'null'], ['', 'empty'], ['named', 'value']])
        ->and($iterator->toArray(true))->toBe(['' => 'empty', 'named' => 'value'])
        ->and($iterator->toArray())->toBe(['null', 'empty', 'value']);
});


it('allows cloning a completed replay without sharing the manual cursor', function (bool $lazy): void {
    $source = $lazy
        ? (static function (): Generator { yield 'first'; yield 'second'; })()
        : ['first', 'second'];
    $original = new ReplayableIterator($source);
    expect($original->toArray())->toBe(['first', 'second']);

    $copy = clone $original;
    $copy->next();

    expect($original->current())->toBe('first')
        ->and($copy->current())->toBe('second')
        ->and($copy->toArray())->toBe(['first', 'second']);
})->with([false, true]);
