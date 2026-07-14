### Task 15: Document + source + workflow-identity rules (8 rules)

**Files:**
- Create: `src/Validation/Rules/DocumentArazzoVersionRule.php`
- Create: `src/Validation/Rules/DocumentInfoRequiredRule.php`
- Create: `src/Validation/Rules/SourceUniqueNameRule.php`
- Create: `src/Validation/Rules/SourceUrlSyntaxRule.php`
- Create: `src/Validation/Rules/SourceTypeMatchesRule.php`
- Create: `src/Validation/Rules/WorkflowAtLeastOneRule.php`
- Create: `src/Validation/Rules/WorkflowUniqueIdRule.php`
- Create: `src/Validation/Rules/WorkflowIdPatternRule.php`
- Create: `tests/Validation/Rules/DocumentAndSourceRulesTest.php`
- Create: `tests/Validation/Rules/WorkflowIdentityRulesTest.php`

**Interfaces:**
- Consumes: Rule/ErrorCollector/SymbolTable from Task 14, all DTOs.
- Produces: 8 concrete `Rule` implementations. Codes: `document.arazzo_version`, `document.info_required`, `source.unique_name`, `source.url_syntax`, `source.type_matches`, `workflow.at_least_one`, `workflow.unique_id`, `workflow.id_pattern`.

- [ ] **Step 1: Write failing tests for document+source rules**

Create `tests/Validation/Rules/DocumentAndSourceRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\DocumentArazzoVersionRule;
use Alama\LaravelArazzo\Validation\Rules\DocumentInfoRequiredRule;
use Alama\LaravelArazzo\Validation\Rules\SourceTypeMatchesRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUniqueNameRule;
use Alama\LaravelArazzo\Validation\Rules\SourceUrlSyntaxRule;

function baseDoc(string $version = '1.0.0', string $title = 'T', string $ver = '1', array $sources = []): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: $version,
        info: new Info($title, null, null, $ver),
        sourceDescriptions: $sources,
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

it('flags wrong arazzo version', function (): void {
    $doc = baseDoc('2.0.0');
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('document.arazzo_version');
});

it('accepts 1.0.0 version', function (): void {
    $doc = baseDoc();
    $ec = new ErrorCollector();
    (new DocumentArazzoVersionRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});

it('requires info title and version', function (): void {
    $doc = baseDoc(title: '', ver: '');
    $ec = new ErrorCollector();
    (new DocumentInfoRequiredRule())->check($doc, SymbolTable::build($doc), $ec);
    expect(count($ec->errors()))->toBe(2);
});

it('flags duplicate source names', function (): void {
    $sources = [
        new SourceDescription('api', '/a', SourceType::Openapi),
        new SourceDescription('api', '/b', SourceType::Openapi),
    ];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUniqueNameRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('source.unique_name');
});

it('flags empty source url', function (): void {
    $sources = [new SourceDescription('api', '', SourceType::Openapi)];
    $doc = baseDoc(sources: $sources);
    $ec = new ErrorCollector();
    (new SourceUrlSyntaxRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('SourceTypeMatchesRule passes (enum enforcement is at parser time)', function (): void {
    $doc = baseDoc(sources: [new SourceDescription('api', '/x', SourceType::Openapi)]);
    $ec = new ErrorCollector();
    (new SourceTypeMatchesRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toBe([]);
});
```

Create `tests/Validation/Rules/WorkflowIdentityRulesTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rules\WorkflowAtLeastOneRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowIdPatternRule;
use Alama\LaravelArazzo\Validation\Rules\WorkflowUniqueIdRule;

function docWithWorkflows(array $workflows): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [],
        $workflows,
        new Components([], [], [], []),
        [],
    );
}
function wf(string $id): Workflow
{
    $s = new Step('s', null, 'op', null, null, [], null, [], [], [], []);
    return new Workflow($id, null, null, null, [], [$s], [], [], [], []);
}

it('flags empty workflows list', function (): void {
    $ec = new ErrorCollector();
    (new WorkflowAtLeastOneRule())->check(docWithWorkflows([]), SymbolTable::build(docWithWorkflows([])), $ec);
    expect($ec->errors())->toHaveCount(1);
});

it('flags duplicate workflowIds', function (): void {
    $doc = docWithWorkflows([wf('a'), wf('a')]);
    $ec = new ErrorCollector();
    (new WorkflowUniqueIdRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.unique_id');
});

it('flags bad workflowId pattern', function (): void {
    $doc = docWithWorkflows([wf('bad id!')]);
    $ec = new ErrorCollector();
    (new WorkflowIdPatternRule())->check($doc, SymbolTable::build($doc), $ec);
    expect($ec->errors())->toHaveCount(1)->and($ec->errors()[0]->code)->toBe('workflow.id_pattern');
});
```

- [ ] **Step 2: Run — expect fail**

- [ ] **Step 3: Implement rules**

`src/Validation/Rules/DocumentArazzoVersionRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocumentArazzoVersionRule implements Rule
{
    public function code(): string { return 'document.arazzo_version'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->arazzo !== '1.0.0') {
            $errors->error($this->code(), "Unsupported arazzo version '{$doc->arazzo}'; only '1.0.0' is supported.", '/arazzo');
        }
    }
}
```

`src/Validation/Rules/DocumentInfoRequiredRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class DocumentInfoRequiredRule implements Rule
{
    public function code(): string { return 'document.info_required'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->info->title === '') {
            $errors->error($this->code(), 'info.title must be a non-empty string.', '/info/title');
        }
        if ($doc->info->version === '') {
            $errors->error($this->code(), 'info.version must be a non-empty string.', '/info/version');
        }
    }
}
```

`src/Validation/Rules/SourceUniqueNameRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SourceUniqueNameRule implements Rule
{
    public function code(): string { return 'source.unique_name'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (isset($seen[$s->name])) {
                $errors->error($this->code(), "Duplicate sourceDescription name '{$s->name}'.", "/sourceDescriptions/{$i}/name");
            }
            $seen[$s->name] = true;
        }
    }
}
```

`src/Validation/Rules/SourceUrlSyntaxRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SourceUrlSyntaxRule implements Rule
{
    public function code(): string { return 'source.url_syntax'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->sourceDescriptions as $i => $s) {
            if (trim($s->url) === '') {
                $errors->error($this->code(), 'sourceDescription url must not be empty.', "/sourceDescriptions/{$i}/url");
                continue;
            }
            // Accept absolute URLs and relative paths beginning with '/' or '.'.
            if (str_starts_with($s->url, '/') || str_starts_with($s->url, '.') || str_starts_with($s->url, './') || filter_var($s->url, FILTER_VALIDATE_URL) !== false) {
                continue;
            }
            $errors->error($this->code(), "sourceDescription url '{$s->url}' is not a valid URI or relative path.", "/sourceDescriptions/{$i}/url");
        }
    }
}
```

`src/Validation/Rules/SourceTypeMatchesRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

/**
 * Enum enforcement happens at parse time; this rule exists so that a stable
 * `source.type_matches` code is reserved and so future non-parser-time checks
 * (e.g. "type: arazzo yet url points to an OpenAPI file") can land here.
 */
final class SourceTypeMatchesRule implements Rule
{
    public function code(): string { return 'source.type_matches'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1 — the enum guarantees correctness.
    }
}
```

`src/Validation/Rules/WorkflowAtLeastOneRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowAtLeastOneRule implements Rule
{
    public function code(): string { return 'workflow.at_least_one'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->workflows === []) {
            $errors->error($this->code(), 'Document must declare at least one workflow.', '/workflows');
        }
    }
}
```

`src/Validation/Rules/WorkflowUniqueIdRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowUniqueIdRule implements Rule
{
    public function code(): string { return 'workflow.unique_id'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $seen = [];
        foreach ($doc->workflows as $i => $w) {
            if (isset($seen[$w->workflowId])) {
                $errors->error($this->code(), "Duplicate workflowId '{$w->workflowId}'.", "/workflows/{$i}/workflowId");
            }
            $seen[$w->workflowId] = true;
        }
    }
}
```

`src/Validation/Rules/WorkflowIdPatternRule.php`:

```php
<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class WorkflowIdPatternRule implements Rule
{
    public function code(): string { return 'workflow.id_pattern'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            if (preg_match('/^[A-Za-z0-9_\-]+$/', $w->workflowId) !== 1) {
                $errors->error($this->code(), "workflowId '{$w->workflowId}' must match [A-Za-z0-9_-]+.", "/workflows/{$i}/workflowId");
            }
        }
    }
}
```

- [ ] **Step 4: Run — expect pass**

- [ ] **Step 5: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: document, source, and workflow identity validation rules"
```

---

