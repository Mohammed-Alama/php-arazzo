import React, { useState, useMemo } from 'react';

export default function Sidebar({ endpoints }) {
    const [search, setSearch] = useState('');

    const filteredEndpoints = useMemo(() => {
        if (!search) return endpoints;
        const lowerSearch = search.toLowerCase();
        return endpoints.filter(ep => 
            ep.path.toLowerCase().includes(lowerSearch) || 
            ep.operationId.toLowerCase().includes(lowerSearch) ||
            ep.method.toLowerCase().includes(lowerSearch) ||
            (ep.summary && ep.summary.toLowerCase().includes(lowerSearch))
        );
    }, [endpoints, search]);

    const onDragStart = (event, nodeType, endpointData) => {
        event.dataTransfer.setData('application/reactflow', nodeType);
        event.dataTransfer.setData('application/endpointData', JSON.stringify(endpointData));
        event.dataTransfer.effectAllowed = 'move';
    };

    return (
        <aside className="w-80 flex flex-col bg-gray-900 border-r border-gray-800 h-full">
            <div className="p-4 border-b border-gray-800">
                <h2 className="text-lg font-semibold mb-3 text-white">Endpoints</h2>
                <input 
                    type="text" 
                    placeholder="Search endpoints..." 
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full bg-gray-800 text-white px-3 py-2 rounded border border-gray-700 focus:outline-none focus:border-blue-500 text-sm"
                />
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
                {filteredEndpoints.map((ep) => (
                    <div 
                        key={ep.operationId}
                        className="p-3 bg-gray-800 border border-gray-700 rounded-lg cursor-grab hover:bg-gray-750 transition-colors shadow-sm"
                        draggable
                        onDragStart={(event) => onDragStart(event, 'endpoint', ep)}
                        title={ep.summary || ep.description || ''}
                    >
                        <div className="flex items-center space-x-2 mb-1">
                            <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded text-white ${ep.method === 'GET' ? 'bg-green-600' : ep.method === 'POST' ? 'bg-blue-600' : ep.method === 'PUT' ? 'bg-orange-600' : ep.method === 'DELETE' ? 'bg-red-600' : 'bg-gray-600'}`}>
                                {ep.method}
                            </span>
                            <span className="text-xs font-mono text-gray-300 truncate" title={ep.path}>{ep.path}</span>
                        </div>
                        <div className="text-sm font-semibold text-gray-100 truncate mb-1" title={ep.operationId}>{ep.operationId}</div>
                        {ep.summary && <div className="text-xs text-gray-400 line-clamp-2">{ep.summary}</div>}
                    </div>
                ))}
                {filteredEndpoints.length === 0 && (
                    <div className="text-gray-500 text-sm text-center py-4">No endpoints found.</div>
                )}
            </div>
        </aside>
    );
}
