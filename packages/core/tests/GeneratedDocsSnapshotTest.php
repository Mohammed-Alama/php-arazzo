<?php

declare(strict_types=1);
use ArazzoDocs\ScannedFile;

require_once dirname(__DIR__, 3).'/scripts/generate-docs/Scanner.php';
require_once dirname(__DIR__, 3).'/scripts/generate-docs/NamespaceGraphDoc.php';

it('keys scans by package so State does not collide', function (): void {
    expect(\ArazzoDocs\packageKey('contracts', 'State'))->toBe('contracts:State')
        ->and(\ArazzoDocs\packageKey('runner', 'State'))->toBe('runner:State');
});

it('labels Ast from its real namespace', function (): void {
    expect(\ArazzoDocs\moduleLabel('expression', 'Ast', 'Alama\\Arazzo\\Expression\\Ast'))
        ->toBe('Alama\\Arazzo\\Expression\\Ast');
});

it('keeps same-named modules from different packages separate', function (): void {
    $scans = [
        'contracts' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Contracts\\State',
            className: 'WorkflowContext',
            isInterface: false,
            uses: [],
            useStatements: [],
            content: '',
            package: 'contracts',
        )]],
        'runner' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Runner\\State',
            className: 'WorkflowState',
            isInterface: false,
            uses: ['Alama\\Arazzo\\Contracts\\State\\WorkflowContext'],
            useStatements: [],
            content: '',
            package: 'runner',
        )]],
    ];

    $out = \ArazzoDocs\NamespaceGraphDoc\render($scans);

    expect($out)->toContain('runner_State --> contracts_State')
        ->and($out)->toContain('Alama\\Arazzo\\Contracts\\State')
        ->and($out)->toContain('Alama\\Arazzo\\Runner\\State');
});

it('groups public api by package with facades first', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/public-api.md');

    expect($out)->toContain('## contracts')
        ->and($out)->not->toContain('Alama\\Arazzo\\Data\\Data');
});

it('renders a per-package capability contract', function (): void {
    $doc = file_get_contents(dirname(__DIR__, 3).'/docs/generated/package-contracts.md');

    expect($doc)->toContain('## contracts')
        ->and($doc)->toContain('## expression')
        ->and($doc)->toContain('## document')
        ->and($doc)->toContain('## runner')
        ->and($doc)->toContain('## cli')
        ->and($doc)->toContain('## laravel')
        ->and($doc)->toContain('Deliberately internal')
        ->and($doc)->toContain('### Capabilities')
        ->and($doc)->toContain('Public entry surface')
        ->and($doc)->toContain('Cross-boundary value types');
});

it('excludes @internal-docblocked types from the public api surface', function (): void {
    require_once dirname(__DIR__, 3).'/scripts/generate-docs/PublicApiDoc.php';

    $hidden = new ScannedFile(
        path: '',
        relativeDir: '',
        namespace: 'Alama\\Arazzo\\Expression',
        className: 'HiddenEvaluator',
        isInterface: true,
        uses: [],
        useStatements: [],
        content: "/**\n * Internal seam, not part of the advertised contract.\n *\n * @internal\n */\ninterface HiddenEvaluator\n{\n    public function resolve(): void;\n}\n",
        package: 'expression',
    );
    $visible = new ScannedFile(
        path: '',
        relativeDir: '',
        namespace: 'Alama\\Arazzo\\Expression',
        className: 'VisibleFacade',
        isInterface: true,
        uses: [],
        useStatements: [],
        content: "/**\n * Public entry seam.\n */\ninterface VisibleFacade\n{\n    public function run(): void;\n}\n",
        package: 'expression',
    );

    $out = \ArazzoDocs\PublicApiDoc\render(['expression' => ['_' => [$hidden, $visible]]]);

    expect($out)->toContain('VisibleFacade')
        ->and($out)->not->toContain('HiddenEvaluator');
});

it('renders real cli commands', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/cli-reference.md');

    expect($out)->toContain('Binary: `bin/arazzo`')->and($out)->not->toContain('Console application class missing');
});

it('tracks core emptiness instead of a missing plan', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/modularization-progress.md');

    expect($out)->not->toContain('No modularization plan found');
});

it('keeps generated dir markdown-only', function (): void {
    $json = glob(dirname(__DIR__, 3).'/docs/generated/*.json') ?: [];

    expect($json)->toBe([]);
});

it('derives layer order from composer require', function (): void {
    expect(\ArazzoDocs\packageLayerOrder(dirname(__DIR__, 3)))->toBe(
        ['contracts', 'expression', 'document', 'runner', 'cli', 'laravel'],
    );
});

it('flattens scans deterministically with owning packages', function (): void {
    $scans = [
        'runner' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Runner\\State',
            className: 'WorkflowState',
            isInterface: false,
            uses: [],
            useStatements: [],
            content: '',
            package: 'runner',
        )]],
        'contracts' => ['State' => [new ScannedFile(
            path: 'State',
            relativeDir: 'State',
            namespace: 'Alama\\Arazzo\\Contracts\\State',
            className: 'WorkflowContext',
            isInterface: false,
            uses: [],
            useStatements: [],
            content: '',
            package: 'contracts',
        )]],
    ];

    $flat = \ArazzoDocs\flattenScans($scans);

    expect(array_map(fn ($f) => $f->namespace.'\\'.$f->className, $flat))->toBe([
        'Alama\\Arazzo\\Contracts\\State\\WorkflowContext',
        'Alama\\Arazzo\\Runner\\State\\WorkflowState',
    ]);
});

it('renders update-migration columns in the schema', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/database-schema.md');

    expect($out)->toContain('status')->and($out)->toContain('completed_at');
});

it('labels security sites with owning packages, not core', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/security-surface.md');

    expect($out)->not->toContain('<small>core</small>');
});

it('audits core emptiness and facade seams', function (): void {
    $out = file_get_contents(dirname(__DIR__, 3).'/docs/generated/boundaries-audit.md');

    expect($out)->toContain('core/src')->and($out)->toContain('Interface');
});

it('snapshots every generated doc', function (): void {
    $dir = dirname(__DIR__, 3).'/docs/generated';
    $files = glob($dir.'/*.md') ?: [];

    expect(count($files))->toBe(36);

    foreach ($files as $f) {
        $out = (string) file_get_contents($f);
        expect($out)->not->toBe('')
            ->and($out)->toContain('GENERATED by scripts/generate-docs.php');
    }
});

it('pins the fixed-doc contracts', function (): void {
    $dir = dirname(__DIR__, 3).'/docs/generated';
    $get = fn (string $f): string => (string) file_get_contents($dir.'/'.$f);

    // Task 2: no fictional top-level namespaces, no false violations
    expect($get('namespace-graph.md'))->not->toContain('Alama\\Arazzo\\Ast\\Ast')
        ->and($get('layering.md'))->toContain('No package-level layering violations.');
    // Task 3: package sections, facades surfaced
    expect($get('public-api.md'))->toContain('## expression')
        ->and($get('public-api.md'))->toContain('### `ExpressionEngine`');
    // Task 4: real CLI introspection
    expect($get('cli-reference.md'))->toContain('arazzo run');
    // Task 5: live split tracking, not a missing plan
    expect($get('modularization-progress.md'))->toContain('Facade pairs present');
    // Task 7: migrated schema columns, live state machine
    expect($get('database-schema.md'))->toContain('completed_at')
        ->and($get('state-machine.md'))->toContain('stateDiagram-v2');
    // Task 8: revived expression AST diagram
    expect($get('expression-ast.md'))->toContain('StepRef');
    // Task 9: hard seam rule clean
    expect($get('boundaries-audit.md'))->toContain('no library package references another');
});
