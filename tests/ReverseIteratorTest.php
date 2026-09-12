<?php

declare(strict_types=1);

use Componenta\Stdlib\ReverseIterator;

it('starts invalid until reverse traversal is rewound', function (): void {
    $iterator = new ReverseIterator([1, 2, 3]);

    expect($iterator->valid())->toBeFalse()
        ->and($iterator->current())->toBeNull()
        ->and($iterator->key())->toBeNull();
});

it('iterates arrays in reverse order while preserving keys', function (): void {
    $iterator = new ReverseIterator(['a' => 1, 'b' => 2, 'c' => 3]);

    expect(iterator_to_array($iterator, true))->toBe([
        'c' => 3,
        'b' => 2,
        'a' => 1,
    ]);
});

it('preserves duplicate and null keys from iterator sources', function (): void {
    $source = new class implements Iterator {
        private array $entries = [
            ['same', 1],
            ['same', 2],
            [null, 3],
        ];
        private int $position = 0;

        public function current(): mixed { return $this->entries[$this->position][1] ?? null; }
        public function key(): mixed { return $this->entries[$this->position][0] ?? null; }
        public function next(): void { $this->position++; }
        public function valid(): bool { return $this->position < count($this->entries); }
        public function rewind(): void { $this->position = 0; }
    };

    $result = [];
    foreach (new ReverseIterator($source) as $key => $value) {
        $result[] = [$key, $value];
    }

    expect($result)->toBe([
        [null, 3],
        ['same', 2],
        ['same', 1],
    ]);
});

it('consumes a lazy source only when iteration starts and replays cached values', function (): void {
    $visited = 0;
    $source = (static function () use (&$visited): Generator {
        foreach (['a' => 1, 'b' => 2, 'c' => 3] as $key => $value) {
            $visited++;
            yield $key => $value;
        }
    })();

    $iterator = new ReverseIterator($source);

    expect($visited)->toBe(0);

    $first = iterator_to_array($iterator, true);
    expect($first)->toBe(['c' => 3, 'b' => 2, 'a' => 1])
        ->and($visited)->toBe(3);

    $second = iterator_to_array($iterator, true);
    expect($second)->toBe($first)
        ->and($visited)->toBe(3);
});

it('materializes reverse traversal through toArray', function (): void {
    $iterator = new ReverseIterator(['first' => 1, 'second' => 2]);

    expect($iterator->toArray())->toBe([
        'second' => 2,
        'first' => 1,
    ]);
});

it('creates an independent iterator when replacing the source', function (): void {
    $original = new ReverseIterator([1, 2, 3]);
    $changed = $original->withIterable(['a', 'b']);

    expect(iterator_to_array($original, false))->toBe([3, 2, 1])
        ->and(iterator_to_array($changed, false))->toBe(['b', 'a']);
});

it('handles empty and single-element iterables', function (): void {
    expect(iterator_to_array(new ReverseIterator([]), true))->toBe([])
        ->and(iterator_to_array(new ReverseIterator(['only' => 1]), true))->toBe(['only' => 1]);
});
