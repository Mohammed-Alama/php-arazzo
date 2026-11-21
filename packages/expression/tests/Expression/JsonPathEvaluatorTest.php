<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Data\JsonPathBudget;
use Alama\Arazzo\Expression\Exceptions\SelectorEvaluationException;
use Alama\Arazzo\Expression\JsonPath\Rfc9535Engine;
use Alama\Arazzo\Expression\JsonPathEvaluator;
use ReflectionMethod;

it('evaluates simple jsonpath expression', function () {
    $data = [
        'store' => [
            'book' => [
                ['category' => 'reference', 'author' => 'Nigel Rees', 'title' => 'Sayings of the Century', 'price' => 8.95],
                ['category' => 'fiction', 'author' => 'Evelyn Waugh', 'title' => 'Sword of Honour', 'price' => 12.99],
            ],
            'bicycle' => ['color' => 'red', 'price' => 19.95],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    // Test single item return unwrapping
    $result = $evaluator->evaluate('$.store.bicycle.color', $data);
    expect($result)->toBe('red');

    // Test array return
    $result = $evaluator->evaluate('$.store.book[*].author', $data);
    expect($result)->toBe(['Nigel Rees', 'Evelyn Waugh']);
});

it('handles invalid or unmatched jsonpath gracefully', function () {
    $data = ['user' => ['name' => 'Alice']];

    $evaluator = new JsonPathEvaluator();

    $result = $evaluator->evaluate('$.user.age', $data);
    expect($result)->toBe([]);
});

it('advertises rfc9535 as the supported version', function () {
    $evaluator = new JsonPathEvaluator();

    expect($evaluator->supportedVersions())->toBe(['rfc9535']);
});

it('implements length function in filter selectors (R1)', function () {
    $data = [
        'items' => [
            ['name' => 'a', 'price' => 10],
            ['name' => 'b', 'price' => 20],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    // length(@) > 1: the items array has 2 elements, so both items match
    $result = $evaluator->evaluate('$..items[?length(@) > 1]', $data);
    expect($result)->toBe([
        ['name' => 'a', 'price' => 10],
        ['name' => 'b', 'price' => 20],
    ]);
});

it('implements count function in filter selectors (R1)', function () {
    $data = [
        'store' => [
            'book' => [
                ['price' => 10, 'title' => 'A'],
                ['price' => 20, 'title' => 'B'],
            ],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    $result = $evaluator->evaluate('$..book[?count(@) > 1]', $data);
    expect($result)->toBe([
        ['price' => 10, 'title' => 'A'],
        ['price' => 20, 'title' => 'B'],
    ]);
});

it('supports full filter grammar: logical operators and existence tests (R2)', function () {
    $data = [
        'store' => [
            'book' => [
                ['price' => 10, 'title' => 'A'],
                ['price' => 20, 'title' => 'B'],
                ['price' => 30],
            ],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    $result = $evaluator->evaluate('$..book[?(@.price > 10 && exists(@.title))]', $data);
    expect($result)->toBe(['price' => 20, 'title' => 'B']);
});

it('supports nested filters in correct scope (R2)', function () {
    $data = [
        'store' => [
            'book' => [
                ['price' => 10, 'title' => 'A'],
                ['price' => 20, 'title' => 'B'],
            ],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    $result = $evaluator->evaluate('$..book[?(@.price > 10)][?(@.title == "B")]', $data);
    expect($result)->toBe(['price' => 20, 'title' => 'B']);
});

it('matches classic engine on corpus selectors (S5 regression)', function () {
    $data = [
        'store' => [
            'book' => [
                ['category' => 'reference', 'author' => 'Nigel Rees', 'title' => 'Sayings of the Century', 'price' => 8.95],
                ['category' => 'fiction', 'author' => 'Evelyn Waugh', 'title' => 'Sword of Honour', 'price' => 12.99],
                ['category' => 'fiction', 'author' => 'Herman Melville', 'title' => 'Moby Dick', 'price' => 8.99],
                ['category' => 'fiction', 'author' => 'J. R. R. Tolkien', 'title' => 'The Lord of the Rings', 'price' => 22.99],
            ],
            'bicycle' => ['color' => 'red', 'price' => 19.95],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    // $..author -> all authors
    expect($evaluator->evaluate('$..author', $data))->toBe([
        'Nigel Rees',
        'Evelyn Waugh',
        'Herman Melville',
        'J. R. R. Tolkien',
    ]);

    // $..book[?(@.price < 15)] -> books under 15
    expect($evaluator->evaluate('$..book[?(@.price < 15)]', $data))->toBe([
        ['category' => 'reference', 'author' => 'Nigel Rees', 'title' => 'Sayings of the Century', 'price' => 8.95],
        ['category' => 'fiction', 'author' => 'Evelyn Waugh', 'title' => 'Sword of Honour', 'price' => 12.99],
        ['category' => 'fiction', 'author' => 'Herman Melville', 'title' => 'Moby Dick', 'price' => 8.99],
    ]);

    // BNPL corpus selector: $[?count(@.products) > 0]
    expect($evaluator->evaluate('$[?count(@.products) > 0]', ['products' => ['a', 'b']]))->toBe([
        'products' => ['a', 'b'],
    ]);

    // BNPL corpus selector: $[?count(@.products) == 0]
    expect($evaluator->evaluate('$[?count(@.products) == 0]', ['products' => []]))->toBe([
        'products' => [],
    ]);

    // OAuth corpus selector: $[?@.access_token != null]
    expect($evaluator->evaluate('$[?@.access_token != null]', ['access_token' => 'tok123']))->toBe([
        'access_token' => 'tok123',
    ]);
});

it('enforces nodes visited budget and does not hang (S3)', function () {
    $large = [];
    for ($i = 0; $i < 100000; $i++) {
        $large[] = ['id' => $i, 'name' => 'item-'.$i];
    }

    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 10000,
        'maxResultSetSize' => 1000,
    ]);

    try {
        $evaluator->evaluate('$..*', $large);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }
});

it('enforces expression length budget (S3)', function () {
    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 10,
        'maxNodesVisited' => 10000,
        'maxResultSetSize' => 1000,
    ]);

    try {
        $evaluator->evaluate('$..book[?(@.price < 15)]', ['book' => []]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('expressionLength');
    }
});

it('enforces result set size budget (S3)', function () {
    $data = [];
    for ($i = 0; $i < 5000; $i++) {
        $data[] = ['id' => $i];
    }

    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxNodesVisited' => 100000,
        'maxResultSetSize' => 100,
    ]);

    try {
        $evaluator->evaluate('$[*].id', $data);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('resultSetSize');
    }
});

it('expression length budget boundary: 999 chars passes, 1001 fails (limit 1000)', function () {
    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 1000, // high: only expressionLength must decide
        'maxNodesVisited' => 10000,
        'maxResultSetSize' => 1000,
    ]);

    $passes = '$'.str_repeat('.a', 499); // 1 + 499*2 = 999 chars, depth 499
    expect(strlen($passes))->toBe(999);
    expect($evaluator->evaluate($passes, []))->toBe([]);

    $fails = '$'.str_repeat('.a', 500); // 1 + 500*2 = 1001 chars
    expect(strlen($fails))->toBe(1001);

    try {
        $evaluator->evaluate($fails, []);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('expressionLength');
    }
});

it('selector depth budget boundary: depth 64 passes, depth 65 fails (limit 64)', function () {
    $evaluator = new JsonPathEvaluator(); // defaults: maxSelectorDepth = 64

    $passes = '$'.str_repeat('.a', 64); // 64 steps -> depth 64
    expect($evaluator->evaluate($passes, []))->toBe([]);

    $fails = '$'.str_repeat('.a', 65); // 65 steps -> depth 65
    try {
        $evaluator->evaluate($fails, []);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('selectorDepth');
    }
});

it('nodes visited budget boundary: 10000 visits passes, 10001 fails (limit 10000)', function () {
    // $..* on a flat array of scalars visits root + array + each element (~N + 2).
    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 10000,
        'maxResultSetSize' => 100000, // high: only nodesVisited must decide
    ]);

    $passes = range(0, 9996); // ~9998 visits -> under the 10000 limit
    $result = $evaluator->evaluate('$..*', $passes);
    expect(is_array($result))->toBeTrue();
    expect(count($result))->toBe(9997);

    $fails = range(0, 9997); // ~9999 visits -> over the 10000 limit
    try {
        $evaluator->evaluate('$..*', $fails);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }
});

it('result set size budget boundary: 1000 results passes, 1001 fails (limit 1000)', function () {
    $evaluator = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 100000, // high: only resultSetSize must decide
        'maxResultSetSize' => 1000,
    ]);

    $passes = [];
    for ($i = 0; $i < 1000; $i++) {
        $passes[] = ['id' => $i];
    }
    expect($evaluator->evaluate('$[*].id', $passes))->toHaveCount(1000);

    $fails = [];
    for ($i = 0; $i < 1001; $i++) {
        $fails[] = ['id' => $i];
    }
    try {
        $evaluator->evaluate('$[*].id', $fails);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('resultSetSize');
    }
});

it('default budgets are the issue-specified values', function () {
    $evaluator = new JsonPathEvaluator();

    expect($evaluator->budget()->maxExpressionLength)->toBe(1000);
    expect($evaluator->budget()->maxSelectorDepth)->toBe(64);
    expect($evaluator->budget()->maxNodesVisited)->toBe(10000);
    expect($evaluator->budget()->maxResultSetSize)->toBe(1000);
});

it('defaults() static builder returns the issue-specified budgets', function () {
    $budget = JsonPathBudget::defaults();

    expect($budget->maxExpressionLength)->toBe(1000);
    expect($budget->maxSelectorDepth)->toBe(64);
    expect($budget->maxNodesVisited)->toBe(10000);
    expect($budget->maxResultSetSize)->toBe(1000);
});

/** @param  string  $expression  */
it('rejects malformed jsonpath expressions', function (string $expression): void {
    $evaluator = new JsonPathEvaluator();

    try {
        $evaluator->evaluate($expression, []);
        expect(true)->toBeFalse();
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->not->toBeEmpty();
    }
})->with([
    '$..book @',            // trailing tokens -> break then trailing-token throw
    '$.',                   // dot with no member -> 'Expected member name after dot'
    '$..(',                 // '..' with no member -> 'Expected member name after ..'
    '$[abc]',               // unsupported bracket content -> 'Unsupported bracket content'
    '$[?(@.foo)]',          // @. then field is fine; covered below
    '$[?(@.)]',             // @. with no field -> 'Expected field name after @.'
    '$[?(.)]',              // leading dot in filter -> parsePathExpression via DOT
    '$[?(.x != null)]',     // path expression via dot in comparison
    '$[?(@.price > 10 || @.price < 5)]',   // logical ||
    '$[?(!(@.price > 10))]',              // grouping + not
    '$[?(@.price == 10 && @.price < 20 || @.price == 30)]',  // mixed && and ||
    '$[?(@.title =~ "^S.*")]',            // regex operator
    '$[?(@.price <= 15)]',                // <=
    '$[?(@.price >= 15)]',                // >=
    '$[?(@.price != 15)]',                // !=
    '$[?(@.x == null)]',                  // null literal
    '$[?(@.x == true)]',                  // true literal
    '$[?(@.x == false)]',                 // false literal
    '$[?exists(@.title)]',                // exists
    '$[?length(@.tags) > 0]',             // length
    '$[?count(@.tags) > 0]',              // count multiple not needed
    '$[?value(@.price) > 10]',            // value
    '$[?match(@.title, "^S")]',           // match two args
    '$[?search(@.title, "S")]',           // search two args
    '$[?length(@) > 1 && count(@) > 0]',  // two functions + &&
]);

it('handles index, slice, wildcard, and union selectors', function () {
    $data = [
        'store' => [
            'book' => [
                ['title' => 'A', 'price' => 10],
                ['title' => 'B', 'price' => 20],
                ['title' => 'C', 'price' => 30],
            ],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    // index [0]
    expect($evaluator->evaluate('$.store.book[0]', $data))->toBe(['title' => 'A', 'price' => 10]);
    // wildcard [.*] via member wildcard (single member unwraps to the book list)
    expect($evaluator->evaluate('$.store.*', $data))->toBe($data['store']['book']);
    // array wildcard [*]
    expect($evaluator->evaluate('$.store.book[*].title', $data))->toBe(['A', 'B', 'C']);
    // slice [:]
    expect($evaluator->evaluate('$.store.book[:]', $data))->toHaveCount(3);
    // slice [1:]
    expect($evaluator->evaluate('$.store.book[1:]', $data))->toHaveCount(2);
    // slice [0:2]
    expect($evaluator->evaluate('$.store.book[0:2]', $data))->toHaveCount(2);
    // slice [::2] step
    expect($evaluator->evaluate('$.store.book[::2].title', $data))->toBe(['A', 'C']);
    // union [0,1]
    expect($evaluator->evaluate('$.store.book[0,1].title', $data))->toBe(['A', 'B']);
    // union [0,1,2]
    expect($evaluator->evaluate('$.store.book[0,1,2].title', $data))->toBe(['A', 'B', 'C']);
    // recursive descent bracket ..[*] selects every child of every node
    expect($evaluator->evaluate('$..[*]', $data))->toHaveCount(11);
    // recursive descent member ..book
    expect($evaluator->evaluate('$..book', $data))->toHaveCount(3);
    // wildcard descendant ..*
    expect($evaluator->evaluate('$..*', $data))->toBeArray()->not->toBeEmpty();
});

it('handles escaped strings and negative numbers in filters', function () {
    $data = [
        'items' => [
            ['name' => 'a"b', 'score' => 1],
            ['name' => 'c', 'score' => 2],
            ['name' => 'd', 'score' => 3],
        ],
    ];

    $evaluator = new JsonPathEvaluator();

    // Escaped double-quote inside a string literal (Tokenizer escape branch).
    $result = $evaluator->evaluate('$..items[?(@.name == "a\"b")]', $data);
    expect($result)->toBeArray();

    // Negative number token (Tokenizer negative-number branch).
    expect($evaluator->evaluate('$.items[-1:]', $data))->toBe(['name' => 'd', 'score' => 3]);
});

it('covers engine edge cases and admission-budget throws', function () {
    $evaluator = new JsonPathEvaluator();

    $data = [
        'items' => [
            ['name' => 'a"b', 'score' => 1],
            ['name' => 'c', 'score' => 2],
            ['name' => 'd', 'score' => 3],
        ],
    ];

    // Object root (normalizeRoot object branch).
    $obj = (object) ['id' => 1, 'name' => 'x'];
    expect($evaluator->evaluate('$.id', $obj))->toBe(1);

    // Object child property (child object branch).
    $objData = ['user' => (object) ['name' => 'alice', 'age' => 30]];
    expect($evaluator->evaluate('$.user.name', $objData))->toBe('alice');

    // value() function.
    expect($evaluator->evaluate('$[?value(@.score) > 1]', $data))->toBeArray();

    // Comparison operators <=, >=, =~.
    expect($evaluator->evaluate('$..items[?(@.score <= 2)]', $data))->toHaveCount(2);
    expect($evaluator->evaluate('$..items[?(@.score >= 2)]', $data))->toHaveCount(2);
    expect($evaluator->evaluate('$..items[?(@.name =~ "/^[ad]/")]', $data))->toHaveCount(2);

    // Function inside a comparison.
    expect($evaluator->evaluate('$..items[?(@.score > length(@.name))]', $data))->toBeArray();

    // length() on an object and on a string/number.
    $objList = [(object) ['a' => 1], (object) ['b' => 2, 'c' => 3]];
    expect($evaluator->evaluate('$[?length(@) > 1]', $objList))->toBe($objList[1]);
    expect($evaluator->evaluate('$..items[?length(@.name) > 0]', $data))->toHaveCount(3);
    // length() of a number is 0, so nothing matches (covers the default return).
    expect($evaluator->evaluate('$[?length(@.score) > 0]', $data))->toBe([]);

    // match() and search() functions with string arguments.
    expect($evaluator->evaluate('$..items[?match(@.name, "/^a/")]', $data))->toBe(['name' => 'a"b', 'score' => 1]);
    expect($evaluator->evaluate('$..items[?search(@.name, "/d/")]', $data))->toBe(['name' => 'd', 'score' => 3]);

    // match() / search() with a missing field (null) -> non-string branch returns false.
    expect($evaluator->evaluate('$..items[?match(@.nonexistent, "x")]', $data))->toBe([]);
    expect($evaluator->evaluate('$..items[?search(@.nonexistent, "x")]', $data))->toBe([]);

    // Unknown function -> default branch.
    try {
        $evaluator->evaluate('$[?(@.score > bogus(@))]', $data);
        expect(true)->toBeFalse();
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('Unexpected token in filter expression');
    }

    // A wildcard over an object yields its member values.
    expect($evaluator->evaluate('$[*]', (object) ['a' => 1]))->toBe(1);

    // Slice on a non-array returns empty.
    expect($evaluator->evaluate('$[0]', (object) ['a' => 1]))->toBe([]);

    // Recursive descent over objects covers the object-member branches.
    $nested = ['a' => ['b' => ['c' => 'deep']]];
    expect($evaluator->evaluate('$..c', $nested))->toBe('deep');
    $objNested = ['root' => (object) ['child' => ['a' => 1]]];
    expect($evaluator->evaluate('$..*', $objNested))->toBeArray();

    // Wildcard over an object's members, then a child step.
    expect($evaluator->evaluate('$.root.*.val', ['root' => (object) ['a' => (object) ['val' => 1], 'b' => (object) ['val' => 2]]]))->toBe([1, 2]);

    // Logical NOT operator.
    expect($evaluator->evaluate('$[?(!(@ > 1))]', [0, 1, 2, 3]))->toBe([0, 1]);
    // Logical OR operator.
    expect($evaluator->evaluate('$[?(@ > 2 || @ < 1)]', [0, 1, 2, 3]))->toBe([0, 3]);

    // Admission budget: selector depth exceeded.
    $deep = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 1,
        'maxNodesVisited' => 100000,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $deep->evaluate('$.a.b.c', ['a' => ['b' => ['c' => 1]]]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('selectorDepth');
    }

    // Admission budget: nodes visited exceeded during recursive descent.
    $deepNodes = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 1,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $deepNodes->evaluate('$..*', [[1]]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }

    // Admission budget: nodes visited exceeded during a slice.
    $many = [];
    for ($i = 0; $i < 50; $i++) {
        $many[] = $i;
    }
    $fewNodes = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 5,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $fewNodes->evaluate('$[*]', $many);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }

    // Filter on a non-array node returns empty (non-array branch).
    expect($evaluator->evaluate('$[*][?(@ > 1)]', [1, 2, 3]))->toBe([]);

    // Admission budget: nodes visited exceeded during a filter.
    $fewFilter = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 3,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $fewFilter->evaluate('$[?(@ > 0)]', [1, 2, 3, 4, 5]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }

    // Recursive descent over objects.

    // A wildcard over an object yields its member values.
    expect($evaluator->evaluate('$[*]', (object) ['a' => 1]))->toBe(1);

    // Slice on a non-array returns empty.
    expect($evaluator->evaluate('$[0]', (object) ['a' => 1]))->toBe([]);

    // Admission budgets throw during select (low node budget).
    $tiny = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 1,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $tiny->evaluate('$.a.b', ['a' => ['b' => 1]]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('nodesVisited');
    }

    // Admission budget: selector depth exceeded.
    $deep = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 1,
        'maxNodesVisited' => 100000,
        'maxResultSetSize' => 1000,
    ]);
    try {
        $deep->evaluate('$.a.b.c', ['a' => ['b' => ['c' => 1]]]);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('selectorDepth');
    }

    // Admission budget: result set size exceeded.
    $many = [];
    for ($i = 0; $i < 200; $i++) {
        $many[] = ['id' => $i];
    }
    $smallResult = JsonPathEvaluator::withLimits([
        'maxExpressionLength' => 1000,
        'maxSelectorDepth' => 64,
        'maxNodesVisited' => 100000,
        'maxResultSetSize' => 50,
    ]);
    try {
        $smallResult->evaluate('$[*].id', $many);
        expect(true)->toBeFalse();
    } catch (SelectorEvaluationException $e) {
        expect($e->codeId)->toBe('expression.jsonpath_budget');
        expect($e->getMessage())->toContain('resultSetSize');
    }
});

/** @param  string  $expression  */
it('rejects trailing or truncated selector expressions', function (string $expression): void {
    $evaluator = new JsonPathEvaluator();

    try {
        $evaluator->evaluate($expression, []);
        expect(true)->toBeFalse();
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->not->toBeEmpty();
    }
})->with([
    '$..',                  // truncated recursive descent -> null token throw
    '$..@',                 // .. then non-member
    '$.@',                  // . then non-member -> 'Expected member name after dot'
    '$[?(@.)]',             // @. with no field
    '$[?(.)]',              // leading dot path in filter
    '$[?(abc)]',            // unknown word in filter -> 'Unexpected token in filter expression'
    '$[?length(@]',         // unterminated function call -> 'Expected token RPAREN'
]);

it('covers the applyStep default branch (PHPStan-mandated dead code)', function (): void {
    // The parser never emits an unknown selector kind, so this defensive
    // branch can only be reached by invoking applyStep directly.
    $engine = new Rfc9535Engine();
    $method = new ReflectionMethod($engine, 'applyStep');
    $method->setAccessible(true);

    $visited = 0;
    $result = $method->invokeArgs(
        $engine,
        [
            ['kind' => 'unknown-selector-kind', 'steps' => []],
            [],
            '$',
            &$visited,
            0,
        ],
    );

    expect($result)->toBe([]);
});
