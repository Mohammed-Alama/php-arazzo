<?php

declare(strict_types=1);

use Ecosystem\Renderer;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Renderer.php';

it('renders a watchlist status table with live state for every item', function (): void {
    $rows = [
        [
            'ref' => 'OAI/Arazzo-Specification#533',
            'topic' => 'wsdl',
            'title' => 'feat(spec): add SOAP support',
            'state' => 'merged',
            'base' => 'v1.2-dev',
            'lastChange' => '2026-09-30',
            'roadmap' => ['plan:phase-f-protocol-packages', 'spec:plugin-stack-oms-multiprotocol'],
            'ourIssue' => null,
            'stance' => 'shipped',
            'url' => 'https://github.com/OAI/Arazzo-Specification/pull/533',
            'live' => true,
        ],
        [
            'ref' => 'OAI/Arazzo-Specification#597',
            'topic' => 'meeting',
            'title' => 'Meeting: Oct 14th, 2026',
            'state' => 'open',
            'base' => '—',
            'lastChange' => '2026-10-08',
            'roadmap' => [],
            'ourIssue' => 182,
            'stance' => 'watch',
            'url' => 'https://github.com/OAI/Arazzo-Specification/issues/597',
            'live' => true,
        ],
    ];

    $md = Renderer::renderMarkdown([], '2026-10-16T00:00:00Z', $rows);

    expect($md)->toContain('## Watchlist — tracked upstream items')
        ->toContain('| Ref | Title | State | Base | Last change | Roadmap | Our issue | Stance |')
        ->toContain('`OAI/Arazzo-Specification#533`')
        ->toContain('[feat(spec): add SOAP support](https://github.com/OAI/Arazzo-Specification/pull/533)')
        ->toContain('| merged | v1.2-dev | 2026-09-30 |')
        ->toContain('`OAI/Arazzo-Specification#597`')
        ->toContain('| 182 | watch |')
        // one row per item
        ->and(substr_count($md, '`OAI/Arazzo-Specification#'))->toBe(2);
});

it('notes an empty watchlist explicitly', function (): void {
    $md = Renderer::renderMarkdown([], '2026-10-16T00:00:00Z', []);

    expect($md)->toContain('## Watchlist — tracked upstream items')
        ->toContain('_No watchlist items configured._');
});
