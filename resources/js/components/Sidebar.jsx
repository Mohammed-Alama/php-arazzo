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
