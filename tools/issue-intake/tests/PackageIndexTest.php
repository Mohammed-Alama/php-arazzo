<?php

declare(strict_types=1);

use Alama\Tools\IssueIntake\PackageIndex;

it('renders the epics under a package, sorted by number', function () {
    $index = new PackageIndex;

    $body = $index->render('runner', [
        ['number' => 118, 'title' => 'Retry accounting', 'state' => 'CLOSED', 'stories' => 4],
        ['number' => 87, 'title' => 'CLI surface', 'state' => 'OPEN', 'stories' => 0],
    ]);

    expect($body)->toContain(PackageIndex::START)
        ->and($body)->toContain(PackageIndex::END)
        ->and($body)->toContain('**Every epic under `runner`.** 1 open.')
        ->and(strpos($body, '#87'))->toBeLessThan(strpos($body, '#118'))
        ->and($body)->toContain('| [#118](118) Retry accounting | closed | 4 |');
});

it('says so when a package has no epics yet', function () {
    $body = (new PackageIndex)->render('sources', []);

    expect($body)->toContain('**Every epic under `sources`.** 0 open.')
        ->and($body)->toContain('No epics yet')
        ->and($body)->not->toContain('| Epic |');
});

it('replaces only between the markers, preserving human text around them', function () {
    $index = new PackageIndex;

    $current = "Hand-written preamble.\n\n"
        .$index->render('runner', [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0]])
        ."\nHand-written epilogue.\n";

    $next = $index->splice($current, $index->render('runner', [
        ['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0],
        ['number' => 88, 'title' => 'Docs', 'state' => 'OPEN', 'stories' => 0],
    ]));

    expect($next)->toContain('Hand-written preamble.')
        ->and($next)->toContain('Hand-written epilogue.')
        ->and($next)->toContain('#88')
        ->and(substr_count($next, PackageIndex::START))->toBe(1);
});

it('adopts a body that has no markers yet', function () {
    $next = (new PackageIndex)->splice("An older hand-written body.\n", (new PackageIndex)->render('runner', []));

    expect($next)->toContain('An older hand-written body.')
        ->and($next)->toContain(PackageIndex::START);
});

it('is stable: splicing the same render twice changes nothing', function () {
    $index = new PackageIndex;
    $epics = [['number' => 87, 'title' => 'CLI', 'state' => 'OPEN', 'stories' => 0]];

    $once = $index->splice('', $index->render('runner', $epics));
    $twice = $index->splice($once, $index->render('runner', $epics));

    expect($twice)->toBe($once);
});

it('stubs a new package-epic with an empty index', function () {
    expect((new PackageIndex)->stub('runner'))->toBe((new PackageIndex)->render('runner', []));
});