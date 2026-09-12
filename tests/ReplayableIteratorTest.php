<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('iterates arrays and iterators with their original keys', function (): void {
    $source = ['a' => 1, 'b' => 2, 'c' => 3];

    expect(iterator_to_array(new ReplayableIterator($source), true))->toBe($source)
        ->and(iterator_to_array(new ReplayableIterator(new ArrayIterator($source)), true))->toBe($source);
});

it('does not read a lazy iterator until data is requested', function (): void {
    $reads = 0;
    $source = (static function () use (&$reads): Generator {
        foreach ([1, 2, 3] as $value) {
            $reads++;
            yield $value;
        }
    })();

    $iterator = new ReplayableIterator($source);

    expect($reads)->toBe(0)
        ->and($iterator->current())->toBe(1)
        ->and($reads)->toBe(1);

    $iterator->next();

    expect($iterator->current())->toBe(2)
        ->and($reads)->toBe(2);
});

it('handles nested IteratorAggregate sources', function (): void {
    $source = new class implements IteratorAggregate {
        public function getIterator(): Traversable
        {
            return new class implements IteratorAggregate {
                public function getIterator(): Traversable
                {
                    return new ArrayIterator(['nested' => 'value']);
                }
            };
        }
    };

    expect((new ReplayableIterator($source))->toArray(preserveKeys: true))
        ->toBe(['nested' => 'value']);
});

it('replays a one-shot generator after it has been cached', function (): void {
    $source = (static function (): Generator {
        yield 1;
        yield 2;
        yield 3;
    })();
    $iterator = new ReplayableIterator($source);

    $first = iterator_to_array($iterator, false);
    $iterator->rewind();
    $second = iterator_to_array($iterator, false);

    expect($first)->toBe([1, 2, 3])
        ->and($second)->toBe($first);
});

it('does not lose source elements when next is called before current', function (): void {
    $source = (static function (): Generator {
        yield 'first';
        yield 'second';
        yield 'third';
    })();
    $iterator = new ReplayableIterator($source);

    expect($iterator->valid())->toBeTrue();
    $iterator->next();

    expect($iterator->current())->toBe('second');

    $iterator->rewind();

    expect($iterator->toArray())->toBe(['first', 'second', 'third']);
});

it('preserves skipped elements across multiple next calls', function (): void {
    $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3, 4, 5]));

    $iterator->next();
    $iterator->next();
    $iterator->next();

    expect($iterator->current())->toBe(4);

    $iterator->rewind();

    expect($iterator->toArray())->toBe([1, 2, 3, 4, 5]);
});

it('retains null and duplicate source keys during iteration', function (): void {
    $source = new class implements Iterator {
        private array $items = [
            [null, 'null-value'],
            ['a', 1],
            ['a', 2],
            ['b', 3],
        ];
        private int $position = 0;

        public function current(): mixed { return $this->items[$this->position][1] ?? null; }
        public function key(): mixed { return $this->items[$this->position][0] ?? null; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->items); }
        public function rewind(): void { $this->position = 0; }
    };

    $result = [];
    foreach (new ReplayableIterator($source) as $key => $value) {
        $result[] = [$key, $value];
    }

    expect($result)->toBe([
        [null, 'null-value'],
        ['a', 1],
        ['a', 2],
        ['b', 3],
    ]);
});

it('preserves all duplicate-key values without keys and uses last value with keys', function (): void {
    $factory = static fn() => new class implements Iterator {
        private array $items = [['a', 1], ['a', 2]];
        private int $position = 0;

        public function current(): mixed { return $this->items[$this->position][1] ?? null; }
        public function key(): mixed { return $this->items[$this->position][0] ?? null; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->items); }
        public function rewind(): void { $this->position = 0; }
    };

    expect((new ReplayableIterator($factory()))->toArray())->toBe([1, 2])
        ->and((new ReplayableIterator($factory()))->toArray(preserveKeys: true))->toBe(['a' => 2]);
});

it('rewinds after partial traversal and can be rewound repeatedly', function (): void {
    $iterator = new ReplayableIterator(new ArrayIterator([1, 2, 3]));

    expect($iterator->current())->toBe(1);
    $iterator->next();
    expect($iterator->current())->toBe(2);

    $iterator->rewind();
    expect(iterator_to_array($iterator, false))->toBe([1, 2, 3]);

    $iterator->rewind();
    expect(iterator_to_array($iterator, false))->toBe([1, 2, 3]);
});

it('toArray forces the source to finish without changing the direct cursor position', function (): void {
    $reads = 0;
    $source = (static function () use (&$reads): Generator {
        foreach ([1, 2, 3, 4] as $value) {
            $reads++;
            yield $value;
        }
    })();
    $iterator = new ReplayableIterator($source);

    expect($iterator->current())->toBe(1);
    $iterator->next();
    expect($iterator->current())->toBe(2)
        ->and($reads)->toBe(2);

    expect($iterator->toArray())->toBe([1, 2, 3, 4])
        ->and($reads)->toBe(4)
        ->and($iterator->current())->toBe(2);
});

it('converts sequential and associative inputs with optional key preservation', function (): void {
    expect((new ReplayableIterator([1, 2, 3]))->toArray())->toBe([1, 2, 3])
        ->and((new ReplayableIterator([1, 2, 3]))->toArray(preserveKeys: true))->toBe([0 => 1, 1 => 2, 2 => 3])
        ->and((new ReplayableIterator(['a' => 1, 'b' => 2]))->toArray())->toBe([1, 2])
        ->and((new ReplayableIterator(['a' => 1, 'b' => 2]))->toArray(preserveKeys: true))->toBe(['a' => 1, 'b' => 2]);
});

it('count consumes the remaining lazy source once and is idempotent', function (): void {
    $reads = 0;
    $source = (static function () use (&$reads): Generator {
        for ($i = 0; $i < 5; $i++) {
            $reads++;
            yield $i;
        }
    })();
    $iterator = new ReplayableIterator($source);

    expect($reads)->toBe(0)
        ->and(count($iterator))->toBe(5)
        ->and($reads)->toBe(5)
        ->and(count($iterator))->toBe(5)
        ->and($reads)->toBe(5);
});

it('handles empty and single-element iterators', function (): void {
    $empty = new ReplayableIterator(new ArrayIterator([]));

    expect($empty->valid())->toBeFalse()
        ->and($empty->current())->toBeNull()
        ->and($empty->key())->toBeNull()
        ->and(count($empty))->toBe(0)
        ->and($empty->toArray())->toBe([]);

    $single = new ReplayableIterator(new ArrayIterator(['only' => 'one']));

    expect($single->valid())->toBeTrue()
        ->and($single->key())->toBe('only')
        ->and($single->current())->toBe('one');

    $single->next();

    expect($single->valid())->toBeFalse()
        ->and(count($single))->toBe(1);
});

it('returns null current and key after the direct cursor passes the end', function (): void {
    $iterator = new ReplayableIterator([1]);
    $iterator->next();

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull()
        ->and($iterator->key())->toBeNull();
});

it('preserves mixed values exactly', function (): void {
    $object = new stdClass();
    $source = [
        'null' => null,
        'bool' => false,
        'int' => 0,
        'float' => 0.0,
        'string' => '',
        'array' => [],
        'object' => $object,
    ];

    expect((new ReplayableIterator($source))->toArray(preserveKeys: true))->toBe($source);
});
