# Arazzo React Flow UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a React Flow UI that allows users to drag-and-drop OpenAPI endpoints to define workflow intent, and sends this intent to the AI Generator backend.

**Architecture:** A Laravel backend provides two API endpoints (`/api/arazzo/endpoints` and `/api/arazzo/generate`) utilizing the existing `SourceResolver` and `ArazzoGenerator`. The frontend is a React SPA embedded in a Laravel view, using `reactflow` for the node canvas and `@monaco-editor/react` for the YAML viewer.

**Tech Stack:** PHP 8.2+, Laravel, React, Vite, React Flow, TailwindCSS, Monaco Editor.

---

### Task 1: Backend API Controllers and Routes

**Files:**
- Create: `src/Http/Controllers/ArazzoApiController.php`
- Modify: `src/LaravelArazzoServiceProvider.php` (to register routes)
- Create: `tests/Http/Controllers/ArazzoApiControllerTest.php`

- [ ] **Step 1: Write test for API Controller**

```php
// tests/Http/Controllers/ArazzoApiControllerTest.php
use Alama\LaravelArazzo\Resolution\SourceResolver;
use Alama\LaravelArazzo\Resolution\ResolvedSource;
use Alama\LaravelArazzo\Generator\ArazzoGenerator;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Assuming routes are registered by the service provider in the testing environment
});

it('returns endpoints list from openapi spec', function () {
    $resolver = Mockery::mock(SourceResolver::class);
    $resolved = Mockery::mock(ResolvedSource::class);
    
    $resolved->shouldReceive('extract')->with('/')->andReturn([
        'paths' => [
            '/test' => [
                'get' => ['operationId' => 'getTest', 'summary' => 'Test', 'tags' => ['API']]
            ]
        ]
    ]);
    
    $resolver->shouldReceive('resolve')->andReturn($resolved);
    $this->app->instance(SourceResolver::class, $resolver);
    
    getJson('/api/arazzo/endpoints?spec=fake.yaml')
        ->assertStatus(200)
        ->assertJson([
            ['method' => 'get', 'path' => '/test', 'operationId' => 'getTest']
        ]);
});

it('generates yaml from graph', function () {
    $generator = Mockery::mock(ArazzoGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn('generated_yaml');
    $this->app->instance(ArazzoGenerator::class, $generator);
    
    postJson('/api/arazzo/generate', [
        'openapi' => 'fake.yaml',
        'graph' => ['nodes' => [], 'edges' => []]
    ])
        ->assertStatus(200)
        ->assertJson(['yaml' => 'generated_yaml']);
});
```

- [ ] **Step 2: Run tests to see failure**

Run: `rtk php artisan test --filter ArazzoApiControllerTest`
Expected: FAIL (routes/controllers don't exist)

- [ ] **Step 3: Implement ArazzoApiController**

```php
// src/Http/Controllers/ArazzoApiController.php
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
```

- [ ] **Step 4: Register Routes in Service Provider**

In `src/LaravelArazzoServiceProvider.php`, update the `packageRegistered` or `packageBooted` method:

```php
public function packageBooted(): void
{
    \Illuminate\Support\Facades\Route::prefix('api/arazzo')
        ->middleware('api') // Or auth:sanctum if required
        ->group(function () {
            \Illuminate\Support\Facades\Route::get('/endpoints', [\Alama\LaravelArazzo\Http\Controllers\ArazzoApiController::class, 'endpoints']);
            \Illuminate\Support\Facades\Route::post('/generate', [\Alama\LaravelArazzo\Http\Controllers\ArazzoApiController::class, 'generate']);
        });
}
```
*(Ensure `spatie/laravel-package-tools` `hasRoute()` or manual routing is correctly set up).*

- [ ] **Step 5: Run tests to verify pass**

Run: `rtk php artisan test --filter ArazzoApiControllerTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
rtk git add src/Http/ src/LaravelArazzoServiceProvider.php tests/Http/
rtk git commit -m "feat: implement arazzo api controllers for ui"
```

---

### Task 2: Frontend Setup

**Files:**
- Modify: `package.json`
- Modify: `vite.config.js`
- Create: `resources/views/arazzo.blade.php`

- [ ] **Step 1: Install Dependencies**

```bash
rtk npm install react react-dom reactflow @monaco-editor/react
rtk npm install --save-dev @vitejs/plugin-react
```

- [ ] **Step 2: Update Vite Config**

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx', 'resources/js/arazzo-ui.jsx'],
            refresh: true,
        }),
        react(),
    ],
});
```

- [ ] **Step 3: Create Blade View**

```html
<!-- resources/views/arazzo.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arazzo Workflow Builder</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/arazzo-ui.jsx'])
</head>
<body class="bg-gray-900 text-white">
    <div id="arazzo-root"></div>
</body>
</html>
```

- [ ] **Step 4: Commit**

```bash
rtk git add package.json package-lock.json vite.config.js resources/views/arazzo.blade.php
rtk git commit -m "chore: setup react, vite, and reactflow dependencies"
```

---

### Task 3: React Components (Canvas & Sidebar)

**Files:**
- Create: `resources/js/arazzo-ui.jsx`
- Create: `resources/js/components/Sidebar.jsx`
- Create: `resources/js/components/EndpointNode.jsx`

- [ ] **Step 1: Create Custom Node**

```jsx
// resources/js/components/EndpointNode.jsx
import React from 'react';
import { Handle, Position } from 'reactflow';

export default function EndpointNode({ data }) {
    const methodColors = {
        GET: 'bg-green-600',
        POST: 'bg-blue-600',
        PUT: 'bg-orange-600',
        DELETE: 'bg-red-600',
    };
    
    return (
        <div className="bg-gray-800 border border-gray-700 rounded-md shadow-lg flex flex-col min-w-[200px]">
            <Handle type="target" position={Position.Left} className="w-3 h-3 bg-gray-500" />
            <div className="flex items-center px-3 py-2 border-b border-gray-700">
                <span className={`text-xs font-bold px-2 py-1 rounded text-white ${methodColors[data.method] || 'bg-gray-600'}`}>
                    {data.method}
                </span>
                <span className="ml-2 font-mono text-sm truncate">{data.path}</span>
            </div>
            <div className="px-3 py-2 text-xs text-gray-400">
                {data.operationId}
            </div>
            <Handle type="source" position={Position.Right} className="w-3 h-3 bg-gray-500" />
        </div>
    );
}
```

- [ ] **Step 2: Create Sidebar**

```jsx
// resources/js/components/Sidebar.jsx
import React from 'react';

export default function Sidebar({ endpoints }) {
    const onDragStart = (event, nodeType, endpointData) => {
        event.dataTransfer.setData('application/reactflow', nodeType);
        event.dataTransfer.setData('application/endpointData', JSON.stringify(endpointData));
        event.dataTransfer.effectAllowed = 'move';
    };

    return (
        <aside className="w-64 bg-gray-900 border-r border-gray-800 p-4 h-full overflow-y-auto">
            <h2 className="text-lg font-semibold mb-4">Endpoints</h2>
            {endpoints.map((ep) => (
                <div 
                    key={ep.operationId}
                    className="mb-2 p-2 bg-gray-800 border border-gray-700 rounded cursor-grab hover:bg-gray-700"
                    draggable
                    onDragStart={(event) => onDragStart(event, 'endpoint', ep)}
                >
                    <div className="text-xs font-bold text-blue-400">{ep.method}</div>
                    <div className="text-sm font-mono truncate">{ep.path}</div>
                </div>
            ))}
        </aside>
    );
}
```

- [ ] **Step 3: Commit**

```bash
rtk git add resources/js/components/
rtk git commit -m "feat: implement endpoint node and sidebar components"
```

---

### Task 4: Main Application & Code Viewer

**Files:**
- Modify: `resources/js/arazzo-ui.jsx`

- [ ] **Step 1: Build the Main App Layout**

```jsx
// resources/js/arazzo-ui.jsx
import React, { useState, useEffect, useCallback, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import ReactFlow, { addEdge, Background, Controls, ReactFlowProvider, useNodesState, useEdgesState } from 'reactflow';
import 'reactflow/dist/style.css';
import Editor from '@monaco-editor/react';

import Sidebar from './components/Sidebar';
import EndpointNode from './components/EndpointNode';

const nodeTypes = { endpoint: EndpointNode };

function App() {
    const [endpoints, setEndpoints] = useState([]);
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);
    const [yaml, setYaml] = useState('');
    const [loading, setLoading] = useState(false);
    const reactFlowWrapper = useRef(null);

    // Hardcoded openapi spec path for V1
    const openapiSpec = 'api.yaml'; 

    useEffect(() => {
        fetch(`/api/arazzo/endpoints?spec=${openapiSpec}`)
            .then(res => res.json())
            .then(data => setEndpoints(data))
            .catch(err => console.error(err));
    }, []);

    const onConnect = useCallback((params) => setEdges((eds) => addEdge(params, eds)), []);

    const onDrop = useCallback(
        (event) => {
            event.preventDefault();
            const type = event.dataTransfer.getData('application/reactflow');
            const dataStr = event.dataTransfer.getData('application/endpointData');
            
            if (typeof type === 'undefined' || !type) return;

            const reactFlowBounds = reactFlowWrapper.current.getBoundingClientRect();
            // Assuming default viewport. For robust implementation use reactFlowInstance.project
            const position = {
                x: event.clientX - reactFlowBounds.left,
                y: event.clientY - reactFlowBounds.top,
            };

            const endpointData = JSON.parse(dataStr);
            const newNode = {
                id: `${endpointData.operationId}-${Date.now()}`,
                type,
                position,
                data: { ...endpointData, notes: '' },
            };

            setNodes((nds) => nds.concat(newNode));
        },
        [setNodes]
    );

    const onDragOver = useCallback((event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }, []);

    const handleGenerate = async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/arazzo/generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ openapi: openapiSpec, graph: { nodes, edges } })
            });
            const data = await res.json();
            setYaml(data.yaml || 'Error generating YAML');
        } catch (e) {
            setYaml('Error: ' + e.message);
        }
        setLoading(false);
    };

    return (
        <div className="flex h-screen w-full flex-col">
            <header className="bg-gray-900 border-b border-gray-800 p-4 flex justify-between items-center">
                <h1 className="text-xl font-bold">Arazzo Flow Builder</h1>
                <button 
                    onClick={handleGenerate} 
                    disabled={loading}
                    className="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded font-semibold disabled:opacity-50"
                >
                    {loading ? 'Generating...' : 'Generate YAML'}
                </button>
            </header>
            <div className="flex flex-1 overflow-hidden">
                <Sidebar endpoints={endpoints} />
                <div className="flex-1 relative" ref={reactFlowWrapper} onDrop={onDrop} onDragOver={onDragOver}>
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onConnect={onConnect}
                        nodeTypes={nodeTypes}
                        fitView
                    >
                        <Background color="#333" gap={16} />
                        <Controls />
                    </ReactFlow>
                </div>
                {yaml && (
                    <div className="w-1/3 border-l border-gray-800 bg-gray-900">
                        <Editor
                            height="100%"
                            defaultLanguage="yaml"
                            theme="vs-dark"
                            value={yaml}
                            options={{ readOnly: false, minimap: { enabled: false } }}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

const root = createRoot(document.getElementById('arazzo-root'));
root.render(<ReactFlowProvider><App /></ReactFlowProvider>);
```

- [ ] **Step 2: Commit**

```bash
rtk git add resources/js/arazzo-ui.jsx
rtk git commit -m "feat: implement main React Flow application and generation integration"
```
