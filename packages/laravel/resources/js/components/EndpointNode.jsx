import React, { useState } from 'react';
import { Handle, Position, useReactFlow } from 'reactflow';

export default function EndpointNode({ id, data }) {
    const { setNodes } = useReactFlow();
    const [expanded, setExpanded] = useState(false);

    const methodColors = {
        GET: 'bg-green-600',
        POST: 'bg-blue-600',
        PUT: 'bg-orange-600',
        DELETE: 'bg-red-600',
    };

    const handleChange = (field, value) => {
        setNodes((nds) =>
            nds.map((n) => {
                if (n.id === id) {
                    return { ...n, data: { ...n.data, [field]: value } };
                }
                return n;
            })
        );
    };

    return (
        <div className="bg-gray-800 border border-gray-700 rounded-md shadow-lg flex flex-col min-w-[250px] overflow-hidden">
            <Handle type="target" position={Position.Left} className="w-3 h-3 bg-gray-500" />
            
            <div 
                className="flex items-center px-3 py-2 border-b border-gray-700 cursor-pointer hover:bg-gray-750"
                onClick={() => setExpanded(!expanded)}
            >
                <span className={`text-xs font-bold px-2 py-1 rounded text-white ${methodColors[data.method] || 'bg-gray-600'}`}>
                    {data.method}
                </span>
                <span className="ml-2 font-mono text-sm truncate flex-1">{data.path}</span>
                <span className="text-gray-400 text-xs ml-2">{expanded ? '▲' : '▼'}</span>
            </div>
            
            <div className="px-3 py-1 text-[10px] text-gray-500 border-b border-gray-700 bg-gray-850">
                <span className="font-semibold text-gray-400">ID:</span> {data.operationId}
                {data.tags && data.tags.length > 0 && (
                    <span className="ml-2 text-blue-400">[{data.tags.join(', ')}]</span>
                )}
            </div>

            {expanded && (
                <div className="p-3 bg-gray-900 space-y-3">
                    {(data.summary || data.description) && (
                        <div className="mb-2 text-xs text-gray-300 italic border-l-2 border-gray-600 pl-2">
                            {data.summary || data.description}
                        </div>
                    )}
                    <div>
                        <label className="block text-xs font-bold text-gray-400 mb-1">Parameters (Key: Value)</label>
                        <textarea 
                            className="w-full bg-gray-800 text-white text-xs p-2 rounded border border-gray-700 min-h-[40px] focus:outline-none focus:border-blue-500"
                            placeholder="e.g. userId: $steps.fetchUser.outputs.id"
                            value={data.parameters || ''}
                            onChange={(e) => handleChange('parameters', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-gray-400 mb-1">Request Body (JSON/Yaml)</label>
                        <textarea 
                            className="w-full bg-gray-800 text-white text-xs p-2 rounded border border-gray-700 min-h-[40px] focus:outline-none focus:border-blue-500"
                            placeholder="e.g. { name: $inputs.name }"
                            value={data.requestBody || ''}
                            onChange={(e) => handleChange('requestBody', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-gray-400 mb-1">Success Criteria (Expressions)</label>
                        <textarea 
                            className="w-full bg-gray-800 text-white text-xs p-2 rounded border border-gray-700 min-h-[40px] focus:outline-none focus:border-blue-500"
                            placeholder="e.g. $statusCode == 200"
                            value={data.criteria || ''}
                            onChange={(e) => handleChange('criteria', e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-bold text-gray-400 mb-1">Outputs (Key: Value)</label>
                        <textarea 
                            className="w-full bg-gray-800 text-white text-xs p-2 rounded border border-gray-700 min-h-[40px] focus:outline-none focus:border-blue-500"
                            placeholder="e.g. userId: $response.body#/data/id"
                            value={data.outputs || ''}
                            onChange={(e) => handleChange('outputs', e.target.value)}
                        />
                    </div>
                </div>
            )}
            
            <Handle type="source" position={Position.Right} className="w-3 h-3 bg-gray-500" />
        </div>
    );
}
