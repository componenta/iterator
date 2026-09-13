<?php

declare(strict_types=1);

use Componenta\Stdlib\ReverseIterator;

it('preserves iterable keys without coercing them to PHP array keys', function (): void {
    $objectKey = new stdClass();
    $iterator = new ReverseIterator((static function () use ($objectKey): Generator {
        yield null => 'null';
        yield '1' => 'string';
        yield 1 => 'integer';
        yield $objectKey => 'object';
    })());
    $pairs = [];

    foreach ($iterator as $key => $value) {
        $pairs[] = [$key, $value];
    }

    expect($pairs)->toBe([
        [$objectKey, 'object'],
        [1, 'integer'],
        ['1', 'string'],
        [null, 'null'],
    ]);
});


it('preserves every duplicate key and its position when reversing a sequence', function (): void {
    $iterator = new ReverseIterator((static function (): Generator {
        yield 'same' => 'first';
        yield 'other' => 'middle';
        yield 'same' => 'last';
    })());

    $read = static function () use ($iterator): array {
        $pairs = [];
        foreach ($iterator as $key => $value) {
            $pairs[] = [$key, $value];
        }

        return $pairs;
    };

    expect($read())->toBe([['same', 'last'], ['other', 'middle'], ['same', 'first']])
        ->and($read())->toBe([['same', 'last'], ['other', 'middle'], ['same', 'first']]);
});

it('reverses concatenated lists without losing their repeated integer keys', function (): void {
    $iterator = new ReverseIterator((static function (): Generator {
        yield from ['first', 'second'];
        yield from ['third'];
    })());

    expect(iterator_to_array($iterator, false))->toBe(['third', 'second', 'first']);
});


it('reverses keys and values on repeated traversals', function (string $sourceKind): void {
    $values = ['first' => null, 4 => false, 'last' => 0];
    $source = match ($sourceKind) {
        'array' => $values,
        'iterator' => new ArrayIterator($values),
        'generator' => (static function () use ($values): Generator {
            yield from $values;
        })(),
        default => throw new InvalidArgumentException('Unknown source kind: ' . $sourceKind),
    };
    $iterator = new ReverseIterator($source);

    expect($iterator->toArray())->toBe(['last' => 0, 4 => false, 'first' => null])
        ->and(iterator_to_array($iterator))->toBe(['last' => 0, 4 => false, 'first' => null]);
})->with(['array', 'iterator', 'generator']);

it('keeps an empty reverse traversal empty on every rewind', function (string $sourceKind): void {
    $source = $sourceKind === 'array' ? [] : (static function (): Generator {
        yield from [];
    })();
    $iterator = new ReverseIterator($source);

    expect($iterator->toArray())->toBe([])
        ->and(iterator_to_array($iterator))->toBe([])
        ->and($iterator->valid())->toBeFalse();
})->with(['array', 'generator']);

it('consumes a lazy reverse source only on its first traversal', function (): void {
    $reads = 0;
    $iterator = new ReverseIterator((static function () use (&$reads): Generator {
        ++$reads;
        yield 'first' => 'a';
        ++$reads;
        yield 'second' => 'b';
    })());
    expect($reads)->toBe(0);

    expect($iterator->toArray())->toBe(['second' => 'b', 'first' => 'a'])
        ->and(iterator_to_array($iterator))->toBe(['second' => 'b', 'first' => 'a'])
        ->and($reads)->toBe(2);
});
