# Laravel Arazzo — React Flow UI Design

**Status**: Draft
**Created**: 2026-07-14
**Package**: `alama/laravel-arazzo` (or a companion frontend package)
**Namespace**: N/A (Frontend + API controllers)
**Slice**: Visual Intent Capture UI.

---

## 1. Goals & Non-Goals

### Goals

- Provide a drag-and-drop canvas using `reactflow` to visually string together OpenAPI endpoints.
- Capture the user's "workflow intent" (sequence and natural language hints) rather than acting as a strict 1:1 Arazzo visual builder.
- Expose Laravel API endpoints to serve OpenAPI data to the frontend and proxy generation requests to the AI Generator backend.
- Display the AI-generated Arazzo YAML back to the user in a syntax-highlighted editor.

### Non-Goals

- Building complex form fields for every single Arazzo spec property (`successCriteria`, JSON pointers, etc). The AI Generator handles this.
- Bidirectional parsing (we don't currently plan to parse a complex Arazzo YAML *back* into a visual graph, though it could be a future goal).
- Replacing the CLI generator (this is an alternative interface to it).

---

## 2. Architecture

The UI sits on top of our existing `SourceResolver` and `ArazzoGenerator` services.

```
┌─────────────────────────────────┐
│         React Frontend          │
│                                 │
│  [Sidebar]  [Canvas]  [Editor]  │
└───────┬─────────────────▲───────┘
  GET   │ (Endpoints)     │ (YAML)
  POST  ▼ (Graph)         │
┌─────────────────────────────────┐
│        Laravel API Routes       │
│                                 │
│  SourceResolver   ArazzoGenerator
└─────────────────────────────────┘
```

---

## 3. API Boundary

### `GET /api/arazzo/endpoints`
- **Query**: `?spec=path/to/openapi.yaml`
- **Action**: Uses the `SourceResolver` to parse the OpenAPI document. Iterates through the paths and operations.
- **Response**:
  ```json
  [
    { "operationId": "loginUser", "method": "POST", "path": "/login", "summary": "Log in", "tags": ["Auth"] }
  ]
  ```

### `POST /api/arazzo/generate`
- **Body**: 
  ```json
  {
    "openapi": "path/to/openapi.yaml",
    "graph": {
      "nodes": [...],
      "edges": [...]
    }
  }
  ```
- **Action**: Translates the React Flow graph into a natural language "trace" or "goal" string (e.g., "1. Call POST /login. Note: extract token. 2. Call GET /users using the token."). Passes this to `Arazzo::generate()`.
- **Response**: `{ "yaml": "..." }`

---

## 4. Frontend Components

### Stack
- React, Vite (or whatever the host Laravel app uses).
- `reactflow` for the canvas.
- `@monaco-editor/react` for the YAML code viewer.
- TailwindCSS for styling.

### 4.1. Sidebar
Lists the endpoints returned from the API, grouped by their OpenAPI `tags`. Users drag these items onto the React Flow canvas to instantiate `EndpointNode`s.

### 4.2. React Flow Canvas
- **`EndpointNode` (Custom Node)**: Displays the HTTP method as a colored badge (Green for GET, Blue for POST, etc.) alongside the path and `operationId`.
- **Edges**: Connecting nodes establishes the sequence of the workflow.

### 4.3. Properties Panel
When a node or edge is selected, a right-side panel opens.
- **Node Properties**: Displays read-only details from OpenAPI (parameters, request body schema).
- **Intent Notes**: A text area where the user can write instructions for the AI (e.g., *"Map the `data.id` from the previous response to the `userId` path parameter"*). These notes are attached to the node/edge data and sent to the backend.

### 4.4. Code Viewer
Once the user clicks "Generate", the UI hits the POST endpoint and displays a loading spinner. When the response arrives, the YAML is shown in a Monaco Editor instance. The user can copy or save it.

---

## 5. Security & Deployment

Since this involves hitting arbitrary OpenAPI URLs and using LLMs, these API endpoints should be protected (e.g., via `auth:sanctum` or restricted to local development environments only if shipped as a dev-tool package like Laravel Telescope).
