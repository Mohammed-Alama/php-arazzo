# Bridge: Filament (Laravel)

Package: `alama/arazzo-pro-filament` (pro)
Requires: `filament/filament ^3.2`, `alama/laravel-arazzo ^2`.
Depends on: `persist-46-normalized-schema` (migrations + Eloquent models this bridge reads).

> **Scoping note:** earlier drafts of this stub also required `alama/arazzo-pro-observability`
> and `alama/arazzo-pro-ui` — neither exists yet, and requiring them turns the first Filament
> slice into a 3-package dependency chain with nothing built in any of them. Dropped for now:
> build `DefinitionResource`/`ExecutionResource` directly against `alama/laravel-arazzo`'s new
> Eloquent models (`persist-46`). Revisit the pro-package split once there's something real to
> split — see `docs/database-schema.md` and the conversation that led here.

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
