<?php

declare(strict_types=1);

// Scaffolds a validator rule + its Pest test and registers both in RuleSet::default().
//
// Usage:
//   php new-rule.php <name> <dotted.code> [--dry-run]
//
// Examples:
//   php new-rule.php "WorkflowTimeout" "workflow.timeout"
//   php new-rule.php "step-max-retries" "step.max_retries"

$fail = static function (string $msg): never {
    fwrite(STDERR, "error: {$msg}\n");
    exit(2);
};

$root = dirname(__DIR__, 4);
$dryRun = in_array('--dry-run', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => $a !== '--dry-run'));

if (count($args) !== 2) {
    fwrite(STDERR, "usage: php new-rule.php <name> <dotted.code> [--dry-run]\n");
    exit(2);
}

[$rawName, $code] = $args;

// Normalise the class name: kebab/snake/camel input -> PascalCase + mandatory Rule suffix.
$words = preg_split('/[^A-Za-z0-9]+/', $rawName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$class = implode('', array_map(ucfirst(...), $words));
$class = preg_replace('/Rule$/', '', $class) . 'Rule';

if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $class)) {
    $fail("class name '{$class}' is not a valid PHP class name");
}

if (!preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/', $code)) {
    $fail("code '{$code}' must be dotted lower_snake segments, e.g. step.outputs_unique");
}

$rulePath = "{$root}/packages/core/src/Validator/Rules/{$class}.php";
$testPath = "{$root}/packages/core/tests/Validator/Rules/{$class}Test.php";
$rulesetPath = "{$root}/packages/core/src/Validator/RuleSet.php";

foreach ([$rulePath, $testPath] as $p) {
    if (file_exists($p)) {
        $fail(basename($p) . ' already exists');
    }
}

$ruleset = file_get_contents($rulesetPath);
if ($ruleset === false) {
    $fail("cannot read {$rulesetPath}");
}

$listAnchor = "        ], \$disabled, \$strict);";
if (!str_contains($ruleset, 'use Alama\\Arazzo\\Validator\\Rules\\')) {
    $fail('no Rules import block found in RuleSet.php');
}
if (!str_contains($ruleset, $listAnchor)) {
    $fail('instantiation list anchor not found in RuleSet.php');
}

// Insert the use statement in alphabetical position within the Rules import block.
$import = "use Alama\\Arazzo\\Validator\\Rules\\{$class};";
$lines = explode("\n", $ruleset);
$insertAt = null;
$lastRulesImport = null;
foreach ($lines as $i => $line) {
    if (str_starts_with($line, 'use Alama\\Arazzo\\Validator\\Rules\\')) {
        $lastRulesImport = $i;
        if (strcmp($line, $import) > 0 && $insertAt === null) {
            $insertAt = $i;
        }
    }
}
$insertAt ??= ($lastRulesImport !== null ? $lastRulesImport + 1 : null);
if ($insertAt === null) {
    $fail('could not determine import insertion point');
}
array_splice($lines, $insertAt, 0, [$import]);

// Append instantiation at the end of the default() list (order carries no behaviour).
$listIdx = (int) array_search($listAnchor, $lines, true);
array_splice($lines, $listIdx, 0, ["            new {$class}(),"]);
$newRuleset = implode("\n", $lines);

$ruleTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class {{CLASS}} implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // TODO: implement the validation.
    }

    public function code(): string
    {
        return '{{CODE}}';
    }
}

PHP;

$testTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Validation\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rules\{{CLASS}};

it('is a no-op on a minimal document', function (): void {
    $doc = new ArazzoDocument('1.0.0', new Info('T', null, null, '1'), [], [], new Components([], [], [], []), []);
    $ec = new ErrorCollector();
    $r = new {{CLASS}}();
    $r->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([])->and($r->code())->toBe('{{CODE}}');
});

PHP;

$render = static fn (string $t): string => str_replace(['{{CLASS}}', '{{CODE}}'], [$class, $code], $t);

echo "rule:     {$class}\n";
echo "code:     {$code}\n";
echo "import:   line " . ($insertAt + 1) . " of RuleSet.php\n";

if ($dryRun) {
    echo "dry run — no files written\n";
    exit(0);
}

file_put_contents($rulePath, $render($ruleTemplate));
file_put_contents($testPath, $render($testTemplate));
file_put_contents($rulesetPath, $newRuleset);

echo "written:  packages/core/src/Validator/Rules/{$class}.php\n";
echo "written:  packages/core/tests/Validator/Rules/{$class}Test.php\n";
echo "\nnext:\n";
echo "  vendor/bin/pint packages/core/src/Validator/Rules/{$class}.php packages/core/tests/Validator/Rules/{$class}Test.php\n";
echo "  cd packages/core && vendor/bin/pest tests/Validator/Rules/{$class}Test.php\n";
echo "  implement check(), then: composer run analyse\n";
