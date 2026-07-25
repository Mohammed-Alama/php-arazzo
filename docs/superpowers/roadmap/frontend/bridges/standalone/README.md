# Bridge: Standalone (any PHP project)

Package: `alama/arazzo-ui-standalone` (OSS) + optional `alama/arazzo-pro-ui` overlay.

For PHP projects that use no admin framework (Slim, Mezzio, WordPress plugin, raw PHP,
custom SPA host, static hosting with API backend).

## What ships

- `public/arazzo/` — pre-built React bundle, plus one PHP entrypoint (`arazzo-ui.php`) that
  bootstraps `arazzo-core` and serves the observability API.
- CLI: `arazzo ui:install <public-dir>` — copies the bundle into the target's public
  directory and prints an `<iframe>` / `<script>` snippet to embed.
- CLI: `arazzo ui:export` — produces a hostable bundle with a configurable API base URL
  (drop into Netlify/S3/Nginx, point at any `arazzo-core` HTTP endpoint).

## Auth model

By default: none — the standalone bridge assumes the operator will front it with basic auth
at the reverse proxy, an SSO gateway, or the host framework's session.

Pro overlay adds a pluggable `AuthGuardInterface` for embedded token verification if the
host cannot handle it.

## Why this bridge exists

Not every PHP project uses Laravel/Symfony/Drupal, and the pro business shouldn't punish
that. Standalone gives access to observability + designer without opinion. Also the natural
delivery target for pure-CLI + web-monitoring workflows (workers running under supervisord
that need a dashboard).
