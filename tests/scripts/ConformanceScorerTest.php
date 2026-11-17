<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Requires the scorer class directly (scripts are not on the autoloader).
 */
require_once __DIR__.'/../../scripts/ConformanceScorer.php';

/**
 * @param  array<string>  $args
 */
function runConformanceScorer(array $args): Process
{
    $repo = dirname(__DIR__, 2);

    $process = new Process(
        array_merge([PHP_BINARY, $repo.'/scripts/conformance-score.php'], $args),
        $repo,
    );
    $process->run();

    return $process;
}

/**
 * Build a minimal conformance-suite fixture and return its directory.
 *
 * @return array{suite: string, valid: string, invalid: string, exec: string}
 */
function conformanceFixture(): array
{
    $suite = sys_get_temp_dir().'/arazzo-scorer-fixture-'.bin2hex(random_bytes(4));

    $ae = $suite.'/cases/ae/1.0.1';
    $exec = $suite.'/cases/exec/1.0.1';
    $sources = $suite.'/cases/ae/sources';

    foreach ([$ae, $exec, $sources] as $dir) {
        @mkdir($dir, 0777, true);
    }

    // A document that validates cleanly (source present, qualified operationPath).
    file_put_contents($ae.'/valid-doc.arazzo.yaml', <<<'YAML'
arazzo: "1.0.1"
info:
  title: Valid
  version: "1"
sourceDescriptions:
  - name: api
    url: https://example.test/openapi.json
    type: openapi
workflows:
  - workflowId: main
    steps:
      - stepId: s1
        operationPath: "{$sourceDescriptions.api.url}#/paths/~1ping/get"
YAML);

    // A document that is invalid (unsupported Arazzo version).
    file_put_contents($ae.'/invalid-doc.arazzo.yaml', <<<'YAML'
arazzo: "9.9.9"
info:
  title: Bad
  version: "1"
YAML);

    // The execution case's OpenAPI companion (served by the mock petstore).
    file_put_contents($sources.'/petstore.openapi.yaml', <<<'YAML'
openapi: 3.1.0
info:
  title: Conformance Petstore
  version: 1.0.0
servers:
  - url: http://127.0.0.1:4010
paths:
  /pets:
    post:
      operationId: createPet
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [name]
              properties:
                name:
                  type: string
      responses:
        '201':
          description: created
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
                  status:
                    type: string
YAML);

    // The mock petstore the execution case runs against.
    @mkdir($suite.'/scripts', 0777, true);
    file_put_contents($suite.'/scripts/mock-petstore.py', <<<'PY'
#!/usr/bin/env python3
"""Minimal petstore mirror of api-evangelist/arazzo-conformance mock-petstore.py."""
import json, sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlsplit, parse_qs

STATE = {'pets': {}, 'log': [], 'gets': {}}

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def send(self, code, body=None):
        data = b'' if body is None else json.dumps(body).encode()
        self.send_response(code)
        if body is not None: self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers(); self.wfile.write(data)

    def record(self, body):
        u = urlsplit(self.path)
        if u.path.startswith('/__'): return
        STATE['log'].append({'method': self.command, 'path': u.path, 'query': {k: v[0] for k, v in parse_qs(u.query).items()},
                             'headers': {k.lower(): v for k, v in self.headers.items() if k.lower().startswith('x-')}, 'body': body})

    def do_GET(self):
        u = urlsplit(self.path); self.record(None)
        if u.path == '/__log': return self.send(200, STATE['log'])
        if u.path == '/pets':
            limit = int(parse_qs(u.query).get('limit', ['100'])[0])
            return self.send(200, list(STATE['pets'].values())[:limit])
        if u.path.startswith('/pets/'):
            pid = u.path.split('/')[2]; pet = STATE['pets'].get(pid)
            if not pet: return self.send(404, {'message': 'not found'})
            STATE['gets'][pid] = STATE['gets'].get(pid, 0) + 1
            if pet['name'] == 'flaky' and STATE['gets'][pid] == 1: return self.send(404, {'message': 'not yet'})
            return self.send(200, pet)
        self.send(404, {'message': 'no route'})

    def do_POST(self):
        u = urlsplit(self.path)
        raw = self.rfile.read(int(self.headers.get('Content-Length') or 0))
        try: body = json.loads(raw) if raw else None
        except ValueError: body = raw.decode(errors='replace')
        self.record(body)
        if u.path == '/__reset':
            STATE.update(pets={}, log=[], gets={}); return self.send(200, {'ok': True})
        if u.path == '/pets':
            if not isinstance(body, dict) or not body.get('name'): return self.send(400, {'message': 'name is required'})
            pid = f'pet-{len(STATE["pets"]) + 1}'
            STATE['pets'][pid] = {'id': pid, 'name': body['name'], 'status': body.get('status', 'available')}
            return self.send(201, STATE['pets'][pid])
        self.send(404, {'message': 'no route'})

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 4010
    ThreadingHTTPServer(('127.0.0.1', port), Handler).serve_forever()
PY);

    // Execution case: create a pet with a literal payload, expect success, one POST /pets.
    file_put_contents($exec.'/exec-doc.arazzo.yaml', <<<'YAML'
arazzo: 1.0.1
info:
  title: Exec fixture
  version: 1.0.0
sourceDescriptions:
- name: petstore
  url: ../../ae/sources/petstore.openapi.yaml
  type: openapi
workflows:
- workflowId: run
  steps:
  - stepId: create
    operationId: createPet
    requestBody:
      contentType: application/json
      payload:
        name: Rex
    successCriteria:
    - condition: $statusCode == 201
YAML);

    file_put_contents($ae.'/manifest.yml', <<<'YAML'
suite: ae
cases:
- id: valid-doc
  file: valid-doc.arazzo.yaml
  version: 1.0.1
  expect: valid
  level: schema
  rule: a valid document
  spec: https://spec.openapis.org/arazzo/v1.0.1.html
- id: invalid-doc
  file: invalid-doc.arazzo.yaml
  version: 1.0.1
  expect: invalid
  level: schema
  rule: an invalid document
  spec: https://spec.openapis.org/arazzo/v1.0.1.html
YAML);

    file_put_contents($exec.'/manifest.yml', <<<'YAML'
suite: exec
cases:
- id: exec-doc
  file: exec-doc.arazzo.yaml
  version: 1.0.1
  workflow: run
  expect: success
  requests:
  - POST /pets
  match:
    0:
      body:
        name: Rex
  rule: a step creates a resource
  spec: https://spec.openapis.org/arazzo/v1.0.1.html
YAML);

    return ['suite' => $suite, 'valid' => $ae.'/valid-doc.arazzo.yaml', 'invalid' => $ae.'/invalid-doc.arazzo.yaml', 'exec' => $exec.'/exec-doc.arazzo.yaml'];
}

it('writes conformance/score.json with per-case validation verdicts, versions and suite sha', function () {
    $fixture = conformanceFixture();
    $out = sys_get_temp_dir().'/arazzo-score-'.bin2hex(random_bytes(4)).'.json';

    $process = runConformanceScorer([
        '--suite-dir='.$fixture['suite'],
        '--output='.$out,
        '--binary=vendor/bin/arazzi',
        '--sha=fixture-sha',
    ]);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getErrorOutput())->toBe('')
        ->and(is_file($out))->toBeTrue();

    $data = json_decode((string) file_get_contents($out), true, 512, JSON_THROW_ON_ERROR);

    expect($data)->toHaveKey('suite')
        ->and($data)->toHaveKey('sha')
        ->and($data['sha'])->toBe('fixture-sha')
        ->and($data)->toHaveKey('validation')
        ->and($data)->toHaveKey('execution')
        ->and($data['validation'])->toHaveKey('cases')
        ->and($data['execution'])->toHaveKey('cases');

    foreach ($data['validation']['cases'] as $case) {
        expect($case)->toHaveKeys(['id', 'suite', 'version', 'level', 'expect', 'verdict', 'pass']);
    }

    $byId = array_column($data['validation']['cases'], null, 'id');
    // A valid document judged valid passes; an invalid document judged invalid also passes.
    expect($byId['valid-doc']['verdict'])->toBe('valid')
        ->and($byId['valid-doc']['pass'])->toBeTrue();
    expect($byId['invalid-doc']['verdict'])->toBe('invalid')
        ->and($byId['invalid-doc']['pass'])->toBeTrue();

    expect($data['execution']['cases'])->toHaveCount(1);
    expect($data['execution']['cases'][0]['pass'])->toBeTrue();
});
