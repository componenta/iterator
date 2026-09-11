<?php

declare(strict_types=1);

use Componenta\Stdlib\ReplayableIterator;

it('provides independent cursors over one lazy source', function (): void {
    $source = (static function (): Generator {
        yield 'first' => 1;
        yield 'second' => 2;
        yield 'third' => 3;
    })();

    $replayable = new ReplayableIterator($source);
    $first = $replayable->cursor();
    $second = $replayable->cursor();

    expect($first->key())->toBe('first')
        ->and($first->current())->toBe(1)
        ->and($second->key())->toBe('first')
        ->and($second->current())->toBe(1);

    $first->next();
    expect($first->key())->toBe('second')
        ->and($first->current())->toBe(2)
        ->and($second->key())->toBe('first')
        ->and($second->current())->toBe(1);

    $second->next();
    expect($second->key())->toBe('second')
        ->and($second->current())->toBe(2);

    $first->next();
    $second->next();

    expect($first->key())->toBe('third')
        ->and($first->current())->toBe(3)
        ->and($second->key())->toBe('third')
        ->and($second->current())->toBe(3);
});

it('replays duplicate and null keys independently', function (): void {
    $source = new class implements Iterator {
        private array $entries = [
            [null, 'null'],
            ['same', 'first'],
            ['same', 'second'],
        ];
        private int $position = 0;

        public function current(): mixed { return $this->entries[$this->position][1] ?? null; }
        public function key(): mixed { return $this->entries[$this->position][0] ?? null; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->entries); }
        public function rewind(): void { $this->position = 0; }
    };

    $replayable = new ReplayableIterator($source);

    expect(iterator_to_array($replayable->cursor(), false))->toBe(['null', 'first', 'second'])
        ->and(iterator_to_array($replayable->cursor(), false))->toBe(['null', 'first', 'second'])
        ->and($replayable->traversed)->toBeTrue();
});

it('rewinds an advanced source exactly once before the first foreach traversal', function (): void {
    $source = new class implements Iterator {
        private array $values = [1, 2, 3];
        private int $position = 2;
        private int $rewinds = 0;

        public function current(): mixed { return $this->values[$this->position] ?? null; }
        public function key(): mixed { return $this->position; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->values); }

        public function rewind(): void
        {
            $this->rewinds++;

            if ($this->rewinds > 1) {
                throw new LogicException('source may only be rewound once');
            }

            $this->position = 0;
        }
    };

    $replayable = new ReplayableIterator($source);
    $values = [];

    foreach ($replayable as $value) {
        $values[] = $value;
    }

    expect($values)->toBe([1, 2, 3])
        ->and($replayable->traversed)->toBeTrue();
});

it('rejects cyclic IteratorAggregate chains instead of unwrapping forever', function (): void {
    $source = new class implements IteratorAggregate {
        private int $calls = 0;

        public function getIterator(): Traversable
        {
            $this->calls++;

            if ($this->calls > 1) {
                throw new LogicException('cycle guard reached');
            }

            return $this;
        }
    };

    expect(fn() => new ReplayableIterator($source))
        ->toThrow(RuntimeException::class, 'IteratorAggregate cycle detected');
});

it('rejects multi-object IteratorAggregate cycles', function (): void {
    $factory = static fn() => new class implements IteratorAggregate {
        public ?IteratorAggregate $next = null;

        public function getIterator(): Traversable
        {
            return $this->next ?? new EmptyIterator();
        }
    };

    $first = $factory();
    $second = $factory();
    $first->next = $second;
    $second->next = $first;

    expect(fn() => new ReplayableIterator($first))
        ->toThrow(RuntimeException::class, 'IteratorAggregate cycle detected');
});

it('does not use deprecated SplObjectStorage APIs while unwrapping aggregates', function (): void {
    $source = new class implements IteratorAggregate {
        public function getIterator(): Traversable
        {
            return new ArrayIterator([1, 2, 3]);
        }
    };

    set_error_handler(static function (int $severity, string $message): bool {
        if ($severity === E_DEPRECATED && str_contains($message, 'SplObjectStorage')) {
            throw new ErrorException($message);
        }

        return false;
    });

    try {
        $replayable = new ReplayableIterator($source);

        expect($replayable->toArray())->toBe([1, 2, 3]);
    } finally {
        restore_error_handler();
    }
});
