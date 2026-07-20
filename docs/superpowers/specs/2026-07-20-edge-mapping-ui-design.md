# Arazzo Flow Edge Mapping UI Design

## Context
The Arazzo workflow builder currently relies on simple unconfigured edges between nodes to denote sequence. Users must manually type mapping expressions (like `$steps.nodeA.outputs.id`) into the target node's metadata textareas. This is error-prone and doesn't leverage the full power of the OpenAPI specification. The goal is to introduce an "engineer builder" experience where edges act as first-class configuration entities that facilitate visual data mapping between node outputs and inputs.

## Components

### 1. Backend: Data Enrichment (`ArazzoApiController.php`)
To populate dropdowns with valid parameters, the backend needs to send the full schema for each endpoint.
- Update `/api/arazzo/endpoints` to extract:
  - **Parameters**: `query`, `path`, and `header` variables.
  - **RequestBody**: Schema properties expected in the request body.
  - **Responses**: Properties returned in a `2xx` response body.
- These fields will be returned as structured arrays in the JSON response payload.

### 2. Frontend: Edge Configuration Modal
- Add a centralized `EdgeConfigModal` component to the React app.
- Wire up `onEdgeClick` in the main React Flow canvas to set the `selectedEdge` in state, which opens the modal.
- **Modal UI**:
  - Displays the Source Operation ID and Target Operation ID.
  - Contains a dynamic list of mapping rows.
  - Each mapping row has:
    - **Source Dropdown**: Populated by the source node's parsed `responses` schema.
    - **Target Dropdown**: Populated by the target node's parsed `parameters` + `requestBody`.
    - **Delete Button**: To remove the mapping row.
  - "Add Mapping" button to create new rows.
  - "Save" button to commit changes to the edge's `data` object.

### 3. Edge State Management
- Edges in React Flow will now carry a `data` payload: `{ mappings: [ { source: '...', target: '...' } ] }`.
- React Flow's edge update functions will be used to safely mutate the edge array when the modal saves.
- We will use a custom Edge component or label to visually indicate if an edge has configured mappings (e.g., showing a badge with the count of mappings).

### 4. Generator Trace Update
- The `generate` method in `ArazzoApiController.php` currently prints edges as `- Edge: Output of Node X flows to Node Y.`.
- This will be updated to read the edge's `data['mappings']` array and append explicit instructions to the LLM prompt.
- Example: `- Edge: Output of Node X flows to Node Y. Mappings: Source 'data.id' maps to Target 'userId'.`

## Error Handling and Validation
- If an endpoint in the OpenAPI spec lacks detailed schemas (e.g. no response body defined), the dropdowns will fallback to allowing freeform text entry.
- The `EdgeConfigModal` gracefully handles missing `data` objects on legacy edges.

## Testing
- Playwright E2E tests will be updated to click an edge, open the modal, select a mapping, save, and verify that the mapping text is correctly sent in the API generation request payload.
