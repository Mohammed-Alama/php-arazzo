# Arazzo Monorepo

This monorepo hosts the Arazzo workflow engine ecosystem for PHP and Laravel.

## Packages

- **[alama/arazzo-core](packages/core)**: Framework-agnostic Arazzo 1.0.0/1.1.0 workflow engine core (parser, validator, executor, expression resolver).
- **[alama/laravel-arazzo](packages/laravel)**: Laravel bridge for `alama/arazzo-core` (service provider, queue driver, cache lock, Eloquent adapters).

## Testing

```bash
make verify
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
