# Release Checklist

Run top-to-bottom before tagging a release of `alama/arazzo-core` or
`alama/laravel-arazzo`. Every gate must pass in the environment doing the
release; record any external prerequisite that is missing instead of
weakening the gate.

## 1. Behavior gates

```bash
composer test          # pest for packages/core AND packages/laravel
composer analyse       # phpstan for both packages (baseline counts exact)
vendor/bin/pint --test packages/core packages/laravel
```

- [ ] All suites green; zero phpstan/pint findings.
- [ ] Conformance fixtures pass with sync/queue parity
      (`cd packages/core && vendor/bin/pest tests/Conformance`).
- [ ] Property tests green (`vendor/bin/pest tests/Property`).

## 2. Compatibility review

- [ ] PHP version constraints unchanged or widened (`^8.4`).
- [ ] Laravel constraint matrix still accurate in both READMEs and this
      repo's CI matrix.
- [ ] No new runtime dependency added to `packages/laravel` that belongs
      in core (or vice versa). Core stays framework-agnostic — guarded by
      `tests/Architecture/CoreIsFrameworkAgnosticTest.php`.
- [ ] Breaking changes listed under `### Changed` with **BREAKING** prefix
      in `CHANGELOG.md`, plus migration notes in the package's
      `UPGRADING.md`.

## 3. Documentation

- [ ] README quick starts execute against the current constructors
      (no stale signatures).
- [ ] New public classes have a one-line purpose statement in the package
      README or docs.
- [ ] `CHANGELOG.md` updated for every user-visible change since the last tag.

## 4. Clean installation smoke test

```bash
bash scripts/smoke-install.sh
```

- [ ] Script exits 0: both packages install from a clean temporary
      Composer project via path repositories, autoload, and run a minimal
      bootstrap snippet.

## 5. License & metadata

- [ ] `composer.json` `license`, `description`, `keywords` accurate.
- [ ] License headers/files present in both packages.

## 6. Tagging

- [ ] Version bumped in the relevant package `composer.json`.
- [ ] Tag message references the CHANGELOG section.
- [ ] After push: subtree splits publish and packagist picks up both
      packages.
