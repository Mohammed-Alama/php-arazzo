<?php

declare(strict_types=1);

use Alama\Arazzo\Console\Command\RunCommand;
use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceRegistry;
use Alama\Arazzo\Spec\SourceDocument;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Tester\CommandTester;

const PETSTORE_MINI = [
    'openapi' => '3.0.3',
    'info' => ['title' => 'Mini', 'version' => '1'],
    'servers' => [['url' => 'https://mini.test']],
    'paths' => [
        '/ping' => [
            'get' => [
                'operationId' => 'ping',
                'responses' => ['200' => ['description' => 'ok']],
            ],
        ],
    ],
];

function arazzoDoc(string $tmp): void
{
    file_put_contents($tmp, <<<'YAML'
        arazzo: "1.0.0"
        info:
          title: Run CLI
          version: "1"
        sourceDescriptions:
          - name: api
            url: https://mini.test/openapi.json
            type: openapi
        workflows:
          - workflowId: pingFlow
            steps:
              - stepId: ping
                operationPath: "{$sourceDescriptions.api.url}#/paths/~1ping/get"
                successCriteria:
                  - condition: "${response.statusCode} == 200"
        YAML);
}

it('runs a workflow end-to-end through the CLI', function (): void {
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    // PSR-18 client that answers every request with 200 {}
    $client = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, ['Content-Type' => 'application/json'], '{}');
        }
    };

    $command = new RunCommand($client, $registry);
    $tester = new CommandTester($command);

    $doc = sys_get_temp_dir().'/arazzo-cli-run-'.uniqid().'.yaml';
    arazzoDoc($doc);

    $tester->execute(['file' => $doc]);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('pingFlow: succeeded')
        ->and($tester->getDisplay())->toContain('✔ ping');
});

it('fails with a clear message for an unknown workflow id', function (): void {
    $registry = new SourceRegistry(new DefaultSourceResolver([]));
    $registry->register(new SourceDocument('api', SourceType::Openapi, 'https://mini.test/openapi.json', PETSTORE_MINI));

    $command = new RunCommand(null, $registry);
    $tester = new CommandTester($command);

    $doc = sys_get_temp_dir().'/arazzo-cli-unknown-'.uniqid().'.yaml';
    arazzoDoc($doc);

    $tester->execute(['file' => $doc, '--workflow' => 'nope']);

    unlink($doc);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('unknown workflow');
});
