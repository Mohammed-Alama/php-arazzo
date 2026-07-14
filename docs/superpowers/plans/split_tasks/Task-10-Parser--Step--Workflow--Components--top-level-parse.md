### Task 10: Parser — Step + Workflow + Components + top-level parse()

**Files:**
- Modify: `src/Parser/Parser.php` — add public `parse(RawDocument): ArazzoDocument`, and protected `parseStep`, `parseWorkflow`, `parseComponents`.
- Create: `tests/Parser/FullParserTest.php`
- Create: `tests/fixtures/parser/valid-minimal.yaml` (reuse Task 3 fixture path is fine; copy contents to a parser-specific fixture)
- Create: `tests/fixtures/parser/full.yaml` (richer doc exercising every branch)

**Interfaces:**
- Consumes: everything above.
- Produces:
  - `Parser::parse(RawDocument $raw): ArazzoDocument` — public entry.
  - Rejects: missing `arazzo` / `info` / `workflows`, wrong types, empty top-level as per spec (empty workflows list allowed structurally; the validator's `workflow.at_least_one` rule handles that).

- [ ] **Step 1: Add richer fixture**

Create `tests/fixtures/parser/full.yaml`:

```yaml
arazzo: "1.0.0"
info:
  title: Full
  version: "1.0"
  description: Exhaustive fixture
sourceDescriptions:
  - name: api
    url: /openapi.yaml
    type: openapi
workflows:
  - workflowId: main
    summary: Primary workflow
    inputs:
      type: object
      properties:
        userId:
          type: string
    parameters:
      - name: X-Trace
        in: header
        value: "{$inputs.userId}"
    steps:
      - stepId: fetch
        description: Fetch user
        operationId: getUser
        parameters:
          - name: id
            in: query
            value: "{$inputs.userId}"
        successCriteria:
          - condition: "$statusCode == 200"
        outputs:
          user: "{$response.body}"
        onSuccess:
          - name: next
            type: goto
            stepId: post
        onFailure:
          - name: back
            type: retry
            retryAfter: 500
            retryLimit: 2
      - stepId: post
        operationId: postThing
        requestBody:
          contentType: application/json
          payload: "{$steps.fetch.outputs.user}"
          replacements:
            - target: /id
              value: "{$inputs.userId}"
    outputs:
      user: "{$steps.fetch.outputs.user}"
components:
  parameters:
    Trace:
      name: X-Trace
      in: header
      value: "{$inputs.userId}"
  successActions:
    goEnd:
      name: goEnd
      type: end
x-vendor-custom: hello
```

- [ ] **Step 2: Write failing test**

Create `tests/Parser/FullParserTest.php`:

```php
<?php
declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Action\RetryAction;
use Alama\LaravelArazzo\Dto\Action\SuccessGotoAction;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Loader\Loader;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;
use Alama\LaravelArazzo\Loader\SymfonyYamlDecoder;
use Alama\LaravelArazzo\Parser\Parser;

function parseFixture(string $rel): \Alama\LaravelArazzo\Dto\ArazzoDocument {
    $loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
    $raw = $loader->load(__DIR__ . '/../fixtures/parser/' . $rel);
    return (new Parser())->parse($raw);
}

it('parses a full arazzo document', function (): void {
    $doc = parseFixture('full.yaml');

    expect($doc->arazzo)->toBe('1.0.0')
        ->and($doc->info->title)->toBe('Full')
        ->and($doc->sourceDescriptions[0]->type)->toBe(SourceType::Openapi)
        ->and($doc->workflows)->toHaveCount(1);

    $wf = $doc->workflows[0];
    expect($wf->workflowId)->toBe('main')
        ->and($wf->steps)->toHaveCount(2)
        ->and($wf->parameters[0]->name)->toBe('X-Trace')
        ->and($wf->outputs)->toHaveKey('user');

    $s1 = $wf->steps[0];
    expect($s1->onSuccess[0])->toBeInstanceOf(SuccessGotoAction::class)
        ->and($s1->onFailure[0])->toBeInstanceOf(RetryAction::class);

    expect($doc->components->successActions)->toHaveKey('goEnd')
        ->and($doc->specificationExtensions)->toHaveKey('x-vendor-custom');
});
```

- [ ] **Step 3: Run — expect fail**

- [ ] **Step 4: Implement parseStep + parseWorkflow + parseComponents + parse**

Append to `src/Parser/Parser.php`:

```php
    protected function parseStep(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Step
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $requestBody = null;
        if (array_key_exists('requestBody', $obj) && $obj['requestBody'] !== null) {
            $requestBody = $this->parseRequestBody($obj['requestBody'], $ctx->push('requestBody'));
        }

        $criteria = [];
        if (($c = $this->optionalArray($obj, 'successCriteria', $ctx)) !== null) {
            foreach (array_values($c) as $i => $item) {
                $criteria[] = $this->parseSuccessCriterion($item, $ctx->push('successCriteria')->push($i));
            }
        }

        $onSuccess = [];
        if (($o = $this->optionalArray($obj, 'onSuccess', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onSuccess[] = $this->parseSuccessAction($item, $ctx->push('onSuccess')->push($i));
            }
        }

        $onFailure = [];
        if (($o = $this->optionalArray($obj, 'onFailure', $ctx)) !== null) {
            foreach (array_values($o) as $i => $item) {
                $onFailure[] = $this->parseFailureAction($item, $ctx->push('onFailure')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        return new \Alama\LaravelArazzo\Dto\Step(
            stepId:          $this->requireString($obj, 'stepId', $ctx),
            description:     $this->optionalString($obj, 'description', $ctx),
            operationId:     $this->optionalString($obj, 'operationId', $ctx),
            operationPath:   $this->optionalString($obj, 'operationPath', $ctx),
            workflowId:      $this->optionalString($obj, 'workflowId', $ctx),
            parameters:      $parameters,
            requestBody:     $requestBody,
            successCriteria: $criteria,
            onSuccess:       $onSuccess,
            onFailure:       $onFailure,
            outputs:         $outputs,
        );
    }

    protected function parseWorkflow(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Workflow
    {
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = $this->optionalArray($obj, 'inputs', $ctx);

        $dependsOn = [];
        if (($d = $this->optionalArray($obj, 'dependsOn', $ctx)) !== null) {
            foreach (array_values($d) as $i => $item) {
                if (!is_string($item)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('dependsOn')->push($i), 'string', $item,
                    );
                }
                $dependsOn[] = $item;
            }
        }

        $steps = [];
        $rawSteps = $this->requireArray($obj, 'steps', $ctx);
        foreach (array_values($rawSteps) as $i => $item) {
            $steps[] = $this->parseStep($item, $ctx->push('steps')->push($i));
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach (array_values($s) as $i => $item) {
                $successActions[] = $this->parseSuccessAction($item, $ctx->push('successActions')->push($i));
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach (array_values($f) as $i => $item) {
                $failureActions[] = $this->parseFailureAction($item, $ctx->push('failureActions')->push($i));
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach (array_values($p) as $i => $item) {
                $parameters[] = $this->parseParameter($item, $ctx->push('parameters')->push($i));
            }
        }

        $outputs = [];
        if (array_key_exists('outputs', $obj) && $obj['outputs'] !== null) {
            $outputs = $this->parseOutputsMap($obj['outputs'], $ctx->push('outputs'));
        }

        /** @var array<string,mixed>|null $inputs */
        return new \Alama\LaravelArazzo\Dto\Workflow(
            workflowId:     $this->requireString($obj, 'workflowId', $ctx),
            summary:        $this->optionalString($obj, 'summary', $ctx),
            description:    $this->optionalString($obj, 'description', $ctx),
            inputs:         $inputs,
            dependsOn:      $dependsOn,
            steps:          $steps,
            successActions: $successActions,
            failureActions: $failureActions,
            outputs:        $outputs,
            parameters:     $parameters,
        );
    }

    protected function parseComponents(mixed $node, ParseContext $ctx): \Alama\LaravelArazzo\Dto\Components
    {
        if ($node === null) {
            return new \Alama\LaravelArazzo\Dto\Components([], [], [], []);
        }
        $obj = $this->requireObjectMap($node, $ctx);

        $inputs = [];
        if (($i = $this->optionalArray($obj, 'inputs', $ctx)) !== null) {
            foreach ($i as $k => $v) {
                if (!is_array($v)) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('inputs')->push((string) $k), 'object (JSON Schema)', $v,
                    );
                }
                /** @var array<string,mixed> $v */
                $inputs[(string) $k] = $v;
            }
        }

        $parameters = [];
        if (($p = $this->optionalArray($obj, 'parameters', $ctx)) !== null) {
            foreach ($p as $k => $v) {
                $parameters[(string) $k] = $this->parseParameter($v, $ctx->push('parameters')->push((string) $k));
            }
        }

        $successActions = [];
        if (($s = $this->optionalArray($obj, 'successActions', $ctx)) !== null) {
            foreach ($s as $k => $v) {
                $parsed = $this->parseSuccessAction($v, $ctx->push('successActions')->push((string) $k));
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('successActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $successActions[(string) $k] = $parsed;
            }
        }

        $failureActions = [];
        if (($f = $this->optionalArray($obj, 'failureActions', $ctx)) !== null) {
            foreach ($f as $k => $v) {
                $parsed = $this->parseFailureAction($v, $ctx->push('failureActions')->push((string) $k));
                if ($parsed instanceof \Alama\LaravelArazzo\Dto\Reusable) {
                    throw \Alama\LaravelArazzo\Exceptions\ParserException::wrongType(
                        $ctx->push('failureActions')->push((string) $k),
                        'action (not a reusable ref)', $v,
                    );
                }
                $failureActions[(string) $k] = $parsed;
            }
        }

        return new \Alama\LaravelArazzo\Dto\Components($inputs, $parameters, $successActions, $failureActions);
    }

    public function parse(\Alama\LaravelArazzo\Dto\RawDocument $raw): \Alama\LaravelArazzo\Dto\ArazzoDocument
    {
        $ctx = new ParseContext($raw->path);
        $d = $raw->data;

        $arazzo = $this->requireString($d, 'arazzo', $ctx);

        if (!array_key_exists('info', $d)) {
            throw \Alama\LaravelArazzo\Exceptions\ParserException::missingField($ctx, 'info');
        }
        $info = $this->parseInfo($d['info'], $ctx->push('info'));

        $sourceDescriptions = [];
        if (array_key_exists('sourceDescriptions', $d) && $d['sourceDescriptions'] !== null) {
            $list = $this->requireList($d['sourceDescriptions'], $ctx->push('sourceDescriptions'));
            foreach ($list as $i => $item) {
                $sourceDescriptions[] = $this->parseSourceDescription($item, $ctx->push('sourceDescriptions')->push($i));
            }
        }

        $workflows = [];
        if (array_key_exists('workflows', $d) && $d['workflows'] !== null) {
            $list = $this->requireList($d['workflows'], $ctx->push('workflows'));
            foreach ($list as $i => $item) {
                $workflows[] = $this->parseWorkflow($item, $ctx->push('workflows')->push($i));
            }
        }

        $components = $this->parseComponents($d['components'] ?? null, $ctx->push('components'));

        $extensions = [];
        foreach ($d as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'x-')) {
                $extensions[$k] = $v;
            }
        }

        return new \Alama\LaravelArazzo\Dto\ArazzoDocument(
            arazzo:                  $arazzo,
            info:                    $info,
            sourceDescriptions:      $sourceDescriptions,
            workflows:               $workflows,
            components:              $components,
            specificationExtensions: $extensions,
        );
    }
```

- [ ] **Step 5: Run — expect pass**

- [ ] **Step 6: PHPStan + commit**

```bash
vendor/bin/phpstan analyse
git add -A
git commit -m "feat: parse Step, Workflow, Components, and top-level ArazzoDocument"
```

---

