# Bridge: Filament (Laravel)

Package: `alama/arazzo-pro-filament` (pro)
Requires: `filament/filament ^3.2`, `alama/laravel-arazzo ^2`, `alama/arazzo-pro-observability ^1`, `alama/arazzo-pro-ui ^1`.

Primary agency-facing UI surface. Everything in `frontend/features/*` lives here as a
Resource, Page, or Widget under the "Workflows" nav group.

## Registration

```php
// PanelProvider
->plugin(\Alama\Arazzo\Filament\ArazzoPlugin::make())
```

## Feature → surface map

| Feature (roadmap slug)           | Filament surface                          |
|----------------------------------|-------------------------------------------|
| obs-15 graph-explorer            | `WorkflowRunResource` (view page — graph tab)  |
| obs-16 event-ledger              | `EventLedgerResource`                     |
| obs-17 payload-inspector         | Modal on `EventLedgerResource` row        |
| obs-18 retry-controls            | Row action on `WorkflowRunResource`       |
| debug-19 time-travel             | `TimeTravelPage`                          |
| debug-20 jsonpath-diff           | Widget inside `PayloadInspector` modal    |
| debug-21 webhook-interception    | `WebhookInterceptionPage`                 |
| health-22 blast-radius           | Dashboard widget                          |
| health-23 error-triage           | `ErrorTriageResource`                     |
| health-24 golden-path            | Overlay toggle on `WorkflowResource` view |
| perf-25 waterfall                | `WaterfallPage`                           |
| diff-26 version-diff             | Action on `WorkflowResource`              |
| saga-27 saga-tracing             | `SagaTracingPage`                         |
| bridge-28 horizon/telescope      | Cross-links from `WorkflowRunResource`    |
| test-29 dry-run-sandbox          | `DryRunSandboxPage`                       |
| (ai-32) workflow-designer        | `WorkflowDesignerPage`                    |
| (tenant-33) OAK catalog          | `CatalogPage` (from `arazzo-pro-catalog`) |

Real-time updates via Laravel Reverb / Pusher if configured, else polling. Filament policies
integrate with any Laravel auth guard.
