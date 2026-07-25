# Bridge: Standalone Own-UI

Framework-agnostic React SPA served by any PHP host. Reference implementation of every
`frontend/features/*` stub; other bridges are wrappers around this bundle.

## Scope

- Ships as `alama/arazzo-pro-ui` (pro) — React 18 + reactflow + Monaco.
- Talks to `arazzo-pro-observability` HTTP API only (no framework coupling).
- Ships an OSS shell (`alama/arazzo-ui-oss`) with the design-time canvas (already in
  `resources/js/arazzo-ui.jsx`) so any PHP project can drop the UI in without pro packages.

## Delivery surfaces

| Host                       | Integration                                                        |
|----------------------------|--------------------------------------------------------------------|
| Any PHP app                | Publish `public/vendor/arazzo-ui/*` + one `<script>` tag           |
| Laravel (no Filament)      | Blade view + published assets                                      |
| Symfony (no EasyAdmin)     | Twig template + `symfony/asset-mapper`                             |
| Drupal (no admin module)   | Library declaration + block plugin                                 |
| Static hosting             | `arazzo ui:export` produces a hostable bundle; API URL configurable |

## Feature coverage

All 15 `frontend/features/*` stubs. Bridge-specific bridges (filament/easyadmin/drupal-admin)
subclass or embed this bundle rather than reimplementing.

## Auth model

Delegated. Own-ui itself is unauthenticated — expects the host or a reverse-proxy to
authenticate and forward a bearer token. Filament / EasyAdmin / Drupal bridges wire in
their host's auth transparently.
