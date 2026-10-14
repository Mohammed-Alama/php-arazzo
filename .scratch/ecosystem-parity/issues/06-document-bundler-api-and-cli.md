# 06: Implement Arazzo document bundling API and CLI command

**What to build:**
Provide an official document bundler that inlines external `$ref` pointers and source descriptions into a single, fully resolved, self-contained Arazzo document. Expose this through a PHP service `ArazzoBundler::bundle()` and an `arazzo bundle <file>` CLI command.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Create `ArazzoBundler` in `packages/document/src/Bundler/`.
- [ ] Recursively resolve `$ref` pointers pointing to local or remote files, placing referenced schemas and reusable workflow components into the document's `components` section.
- [ ] Implement cycle detection to safely handle shared and circular references.
- [ ] Add `arazzo bundle <path>` command in `packages/cli` with `--output` and `--format [yaml|json]` options.
- [ ] Add unit and integration tests verifying bundling of multi-file Arazzo and OpenAPI schemas.
