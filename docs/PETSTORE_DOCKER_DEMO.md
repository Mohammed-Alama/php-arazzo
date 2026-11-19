# 🐳 Petstore Docker Demo — `arazzo run` CLI Integration Test

This guide demonstrates running the `arazzo` CLI against a real Petstore API
running in Docker.

## Prerequisites

- **Docker** installed and running
- **PHP ^8.4** with Composer installed
- The `arazzo` CLI binary (built from `packages/cli/bin/arazzo`)

---

## Step 1: Start the Petstore API in Docker

```bash
# Start the Petstore API in Docker
docker compose -f docker-compose.arazzo.yaml up -d

# Wait for the API to be ready
curl -f http://localhost:8080/v3/pets
```

Expected output: A JSON array of pets (or an empty array if none exist yet).

```bash
# Stop the demo when done
docker compose -f docker-compose.arazzo.yaml down
```

---

## Step 2: Run the Arazzo Workflow via CLI

```bash
# Execute the workflow against the local Petstore
php packages/cli/bin/arazzo run \
  petstore-workflow.arazzo.yaml \
  --inputs '{"username":"user","password":"pass123"}'
```

### Expected output

```
Workflow execution finished with status: succeeded

 - Step loginStep: Success
 - Step getPetsByStatus: Success
 - Step getPetById: Success
```

Or with full step details:

```
{
  "status": "succeeded",
  "stepResults": {
    "loginStep": { "successful": true, "stepId": "loginStep" },
    "getPetsByStatus": { "successful": true, "stepId": "getPetsByStatus" },
    "getPetById": { "successful": true, "stepId": "getPetById" }
  }
}
```

---

## How It Works

The `petstore-workflow.arazzo.yaml` workflow:

1. **loginStep** — Calls `POST /user/login` with `username` and `password` query parameters. Stores the response body (auth token) as `token`.

2. **getPetsByStatus** — Calls `GET /pet/findByStatus` with `status=available` and `Authorization` header set to the login token. Returns available pets.

3. **getPetById** — Calls `GET /pet/{petId}` using the ID of the first pet from the previous step's response.

Each step validates its response with `successCriteria` (HTTP status must be 200).

---

## Files Involved

| File | Purpose |
|------|---------|
| `docker-compose.arazzo.yaml` | Docker Compose to spin up the Petstore API |
| `petstore-workflow.arazzo.yaml` | The Arazzo workflow document executed by `arazzo run` |
| `packages/cli/bin/arazzo` | The CLI entry point for running workflows |
| `packages/cli/src/Console/Command/RunCommand.php` | The `run` command implementation |

---

## Troubleshooting

### Connection refused to `localhost:8080`

Make sure the Petstore container is running:

```bash
docker compose -f docker-compose.arazzo.yaml up -d
docker compose -f docker-compose.arazzo.yaml ps
```

### 404 or 401 errors

- **404**: The Petstore API path may differ. Check `http://localhost:8080/v3/openapi.json` for the actual endpoint paths.
- **401**: The login credentials may be wrong. The Petstore demo typically accepts any username/password.

### Workflow fails at `getPetById`

If `findPetsByStatus` returns no pets, `petId` will be undefined. Add test data via the Petstore API first:

```bash
curl -X POST http://localhost:8080/v3/pet \
  -H "Content-Type: application/json" \
  -d '{"id":1,"name":"TestPet","status":"available"}'
```

Then rerun the workflow.