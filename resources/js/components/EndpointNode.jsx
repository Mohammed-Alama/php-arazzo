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
