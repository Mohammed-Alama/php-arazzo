# Bridge: EasyAdmin (Symfony)

Package: `alama/arazzo-pro-symfony-easyadmin` (pro, Phase E of the commercial plan)
Requires: `easycorp/easyadmin-bundle ^4`, `alama/symfony-arazzo ^1`, `alama/arazzo-pro-observability ^1`, `alama/arazzo-pro-ui ^1`.

Mirror of the Filament bridge for Symfony projects. Wraps the same `arazzo-pro-ui` React
bundle behind EasyAdmin CRUD controllers, dashboard, and menu.

## Registration

```php
// DashboardController
public function configureMenuItems(): iterable
{
    yield MenuItem::linkToRoute('Workflows', 'fa fa-project-diagram', 'arazzo_dashboard');
    // ... register CRUD controllers below
}
```

## Feature → surface map

Same 15 features from `frontend/features/*` — each becomes an EasyAdmin `CrudController` or
custom dashboard action. Naming mirrors the Filament map (see filament/README.md); only the
framework glue differs.

## Auth model

Uses Symfony Security voters. `ARAZZO_VIEW`, `ARAZZO_RETRY`, `ARAZZO_ADMIN` roles.

## Delivery timing

Phase E of the commercial plan. Ships after Laravel/Filament proves the UX out and generates
first revenue; no Symfony work until then unless a design partner materializes.
