<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol\Rpc\Registrar;

/**
 * Framework-agnostic registration of the RPC vertical slice into the core SPI
 * registries.
 *
 * Seeded at F5.0 to give the (otherwise empty) `src/` a parseable symbol:
 * PHPStan cannot analyse a package with no `.php` under `paths`, and the root
 * `scripts` wiring is reserved for the scaffold tasks (F1.0/F4.0/F5.0), so the
 * package must carry ≥1 real class at scaffold time (F4.0 ruling 1). F5.2
 * replaces this with the real registrar — the proto normalizer, the four
 * variant executors (grpc/grpc-web/twirp/connect) and the response validator.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RpcRegistrar {}
