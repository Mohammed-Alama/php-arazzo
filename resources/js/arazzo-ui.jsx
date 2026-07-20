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

    const [specPath, setSpecPath] = useState('ST_local_api.json');
    const [specInput, setSpecInput] = useState('ST_local_api.json');

    const fetchEndpoints = useCallback(() => {
        setLoading(true);
        fetch(`/api/arazzo/endpoints?spec=${specPath}`)
            .then(res => res.json())
            .then(data => setEndpoints(data))
            .catch(err => console.error(err))
            .finally(() => setLoading(false));
    }, [specPath]);

    useEffect(() => {
        fetchEndpoints();
    }, [fetchEndpoints]);

    const handleLoadSpec = () => {
        setSpecPath(specInput);
    };

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
                body: JSON.stringify({ openapi: specPath, graph: { nodes, edges } })
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
                <h1 className="text-xl font-bold text-white">Arazzo Flow Builder</h1>
                
                <div className="flex items-center space-x-2 flex-1 max-w-xl mx-8">
                    <input 
                        type="text" 
                        value={specInput}
                        onChange={(e) => setSpecInput(e.target.value)}
                        placeholder="Path to OpenAPI Spec (e.g. ST_local_api.json)"
                        className="flex-1 bg-gray-800 text-white px-3 py-2 rounded border border-gray-700"
                    />
                    <button 
                        onClick={handleLoadSpec}
                        disabled={loading}
                        className="bg-gray-700 text-white px-4 py-2 rounded font-semibold hover:bg-gray-600"
                    >
                        Load
                    </button>
                </div>

                <button 
                    onClick={handleGenerate} 
                    disabled={loading}
                    className="bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded font-semibold disabled:opacity-50 whitespace-nowrap"
                >
                    {loading ? 'Processing...' : 'Generate YAML'}
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

const rootElement = document.getElementById('arazzo-root');
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(<ReactFlowProvider><App /></ReactFlowProvider>);
}
