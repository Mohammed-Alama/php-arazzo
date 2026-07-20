# Arazzo UI Refinement Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refine the Arazzo React Flow UI to support advanced node configuration (parameters, criteria, outputs) and dynamic OpenAPI spec loading (to test with real-world `ST_local_api.json`).

---

### Task 1: Dynamic OpenAPI Spec Loading

**Files to modify:**
- `resources/js/arazzo-ui.jsx`

- [ ] **Step 1: Add Spec Input Field**
Update `App` component state to manage `specPath` (default: `ST_local_api.json`). Add an input field and a "Load Spec" button to the header. Update the `useEffect` to fetch endpoints whenever the `specPath` state changes or via explicit load button.

- [ ] **Step 2: Rebuild & Test**
Run `rtk proxy npm run build` and ensure the UI can successfully fetch endpoints from `ST_local_api.json` and render them in the sidebar.

- [ ] **Step 3: Commit**
`rtk proxy git add resources/js/arazzo-ui.jsx && rtk proxy git commit -m "feat: allow dynamic openapi spec loading in ui"`

---

### Task 2: Advanced Node Configuration UI

**Files to modify:**
- `resources/js/components/EndpointNode.jsx`

- [ ] **Step 1: Expand Node UI**
Update `EndpointNode` to include basic forms for Arazzo step details:
- **Parameters**: A textarea or key-value list for `name: expression` (e.g., `userId: $steps.step1.outputs.userId`).
- **Success Criteria**: A textarea for Arazzo expressions (e.g., `$statusCode == 200`).
- **Outputs**: A textarea for key-value extraction (e.g., `userId: $response.body#/data/id`).
Use standard HTML inputs styled with Tailwind classes. Ensure the state updates the React Flow node `data` object using the `data.onChange` or similar pattern (actually, in React Flow custom nodes, you can pass a callback or update via `setNodes`). 

*Tip: A simple way is to dispatch an event or use a global state, but React Flow nodes receive `id` and `data`. You might need to add a generic `updateNodeData` function to `App` and pass it down, or use the `useReactFlow` hook to update the node's data internally.*

- [ ] **Step 2: Add `updateNodeData`**
Use `const { setNodes } = useReactFlow();` inside `EndpointNode` to update its own data fields (`parameters`, `criteria`, `outputs`) when inputs change.

- [ ] **Step 3: Commit**
`rtk proxy git add resources/js/components/EndpointNode.jsx && rtk proxy git commit -m "feat: add advanced node configuration fields"`

---

### Task 3: Enhance Generator Prompt with Node Config

**Files to modify:**
- `src/Http/Controllers/ArazzoApiController.php`

- [ ] **Step 1: Parse Advanced Node Data in Trace**
Update the `generate` method in `ArazzoApiController.php` to include the new fields in the natural language trace. 
For example, if a node has `parameters`, `criteria`, or `outputs`, format them nicely in the trace text so the LLM understands exactly what to map.

```php
// Example addition to the trace loop
if (!empty($node['data']['parameters'])) {
    $trace .= "  - Parameters: " . $node['data']['parameters'] . "\n";
}
if (!empty($node['data']['criteria'])) {
    $trace .= "  - Success Criteria: " . $node['data']['criteria'] . "\n";
}
if (!empty($node['data']['outputs'])) {
    $trace .= "  - Outputs: " . $node['data']['outputs'] . "\n";
}
```

- [ ] **Step 2: Commit**
`rtk proxy git add src/Http/Controllers/ArazzoApiController.php && rtk proxy git commit -m "feat: include advanced node configuration in generator trace"`
