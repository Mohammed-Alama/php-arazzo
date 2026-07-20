<?php

namespace Alama\LaravelArazzo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Enum\SourceType;

class ArazzoApiController extends Controller
{
    public function endpoints(Request $request, SourceResolver $resolver)
    {
        $specPath = $request->query('spec');
        if (!$specPath) {
            return response()->json(['error' => 'spec parameter is required'], 400);
        }

        $source = new SourceDescription('api', $specPath, SourceType::Openapi);
        $resolved = $resolver->resolve($source, getcwd());
        
        $data = $resolved->extract('/');
        $endpoints = [];

        if (isset($data['paths']) && is_array($data['paths'])) {
            foreach ($data['paths'] as $path => $methods) {
                foreach ((array)$methods as $method => $op) {
                    if (is_array($op) && isset($op['operationId'])) {
                        $endpoints[] = [
                            'method' => strtoupper($method),
                            'path' => $path,
                            'operationId' => $op['operationId'],
                            'summary' => $op['summary'] ?? '',
                            'tags' => $op['tags'] ?? ['Default']
                        ];
                    }
                }
            }
        }

        return response()->json($endpoints);
    }

    public function generate(Request $request, ArazzoGenerator $generator)
    {
        $request->validate([
            'openapi' => 'required|string',
            'graph' => 'required|array'
        ]);

        $graph = $request->input('graph');
        
        // Convert graph to a natural language trace for the AI
        $trace = "Workflow Graph Intent:\n";
        foreach ($graph['nodes'] ?? [] as $node) {
            $trace .= "- Node {$node['id']}: Execute {$node['data']['method']} {$node['data']['path']} (Operation: {$node['data']['operationId']}). Notes: {$node['data']['notes']}\n";
        }
        foreach ($graph['edges'] ?? [] as $edge) {
            $trace .= "- Edge: Output of Node {$edge['source']} flows to Node {$edge['target']}. Notes: {$edge['data']['notes']}\n";
        }

        $yaml = $generator->generate($request->input('openapi'), $trace);

        return response()->json(['yaml' => $yaml]);
    }
}
