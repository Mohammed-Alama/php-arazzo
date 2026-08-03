# Upgrading `alama/laravel-arazzo` from 1.x to 2.x

## TL;DR

```bash
composer require alama/laravel-arazzo:^2.0@alpha
```

Everything else keeps working. Old namespaces alias to new ones automatically.

## What changed

- `alama/laravel-arazzo` is now a thin Laravel bridge over the new framework-agnostic `alama/arazzo-core` (installed as a transitive dep).
- All framework-agnostic classes moved from `Alama\LaravelArazzo\*` to `Alama\Arazzo\*`. All Laravel-specific classes moved from `Alama\LaravelArazzo\Laravel\*` to `Alama\Arazzo\Laravel\*`.

## Do I need to update my code?

Not yet — every old FQCN resolves to its new location via `class_alias`.
