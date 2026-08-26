<?php

declare(strict_types=1);

namespace Alama\Arazzo\Normalizer;

/**
 * OpenAPI 3.1 normalizer.
 *
 * For everything this pipeline consumes (servers precedence, parameter
 * merging, local `$ref`s in parameters/requestBodies, response media
 * types), a 3.1 document is structurally identical to 3.0, so the 3.0
 * logic is reused verbatim.
 *
 * Known 3.1-specific divergences deliberately out of scope here:
 * - JSON Schema 2020-12 dialect details (`type` arrays such as
 *   `["string","null"]`, `$dynamicRef`, vocabulary keywords) are passed
 *   through as opaque schema data.
 * - The `webhooks` map is not normalized (no Arazzo step can target it).
 */
class OpenApi31Normalizer extends OpenApi30Normalizer {}
