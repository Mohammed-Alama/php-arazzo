# Bridge: Drupal Admin

Package: `alama/arazzo-pro-drupal-admin` (pro, Phase E of the commercial plan)
Requires: Drupal 10 or 11, `alama/drupal-arazzo ^1`, `alama/arazzo-pro-observability ^1`, `alama/arazzo-pro-ui ^1`.

Drupal sub-module embedding the `arazzo-pro-ui` React bundle inside Drupal's admin theme.

## Registration

```yaml
# arazzo_pro_admin.info.yml
name: 'Arazzo Pro Admin'
type: module
core_version_requirement: ^10 || ^11
dependencies:
  - alama/drupal_arazzo
  - alama/arazzo_pro_observability
```

Admin menu entry registered under `/admin/config/arazzo`.

## Feature → surface map

Same 15 features from `frontend/features/*`, delivered as Drupal admin routes + blocks.
Naming mirrors the Filament map; Drupal-specific glue lives in `.routing.yml`,
`.services.yml`, and `.permissions.yml`.

## Auth model

Uses Drupal's built-in permissions:

- `view arazzo runs`
- `retry arazzo runs`
- `administer arazzo`

Assign to roles via `/admin/people/permissions`.

## Delivery timing

Phase E. Only prioritized above Symfony if a design partner or funded contract materializes.
