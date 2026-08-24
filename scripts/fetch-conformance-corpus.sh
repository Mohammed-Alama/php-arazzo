#!/usr/bin/env bash
#
# Refreshes the vendored OAI conformance corpus from upstream.
# See packages/core/tests/Conformance/corpus/oai/README.md for provenance.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIR="$ROOT/packages/core/tests/Conformance/corpus/oai"
BASE="https://raw.githubusercontent.com/OAI/Arazzo-Specification/main/examples"

mkdir -p "$DIR/1.0.0" "$DIR/1.1.0" "$DIR/remotes"

FILES_100=(
    ExtendedParametersExample.arazzo.yaml
    FAPI-PAR.arazzo.yaml FAPI-PAR.openapi.yaml
    LoginAndRetrievePets.arazzo.yaml
    bnpl-arazzo.yaml bnpl-openapi.yaml
    oauth.arazzo.yaml oauth.openapi.yaml
    pet-coupons.arazzo.yaml pet-coupons.openapi.yaml
)

for f in "${FILES_100[@]}"; do
    curl -sfL --max-time 30 "$BASE/1.0.0/$f" -o "$DIR/1.0.0/$f"
    echo "fetched 1.0.0/$f"
done

curl -sfL --max-time 30 "$BASE/1.1.0/pet-asyncapi.yaml" -o "$DIR/1.1.0/pet-asyncapi.yaml"
echo "fetched 1.1.0/pet-asyncapi.yaml"

curl -sfL --max-time 60 \
    https://raw.githubusercontent.com/swagger-api/swagger-petstore/master/src/main/resources/openapi.yaml \
    -o "$DIR/remotes/swagger-petstore-openapi.yaml"
echo "fetched remotes/swagger-petstore-openapi.yaml"

echo "corpus refreshed"
