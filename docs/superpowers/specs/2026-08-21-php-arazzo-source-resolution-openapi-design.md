# Source Resolution and OpenAPI Operations

## Goal

Resolve source descriptions, OpenAPI documents, and operation references according to the official Arazzo specification.

## Resolution

Resolve every source description by declared name. Support multiple OpenAPI sources, local/file/http/https fetchers, relative URLs based on retrieval URI, scoped document caching, circular-reference detection, source-aware errors, and source identity in traces.

Resolve `operationId`, `$sourceDescriptions.<name>.<operationId>`, and `operationPath`. Ambiguous or unresolved references must fail before dispatch. The implementation must never select a source based on list order.

## Normalized operation

Introduce a normalized operation abstraction independent of `cebe/php-openapi`. It exposes HTTP method, resolved URL/server, path/query/header/cookie parameters, request-body content types and schema, security requirements, response definitions, source identity, and operation identity.

The normalizer supports every OpenAPI version declared by the PHP package and is shared by synchronous and queued execution.

## Acceptance

Fixtures with two OpenAPI sources must dispatch to the correct source. Relative URLs must work from local and HTTP documents. Invalid references must produce typed resolution errors. Operation lookup must not depend on source ordering. Supported OpenAPI versions must have explicit fixtures and documentation.
