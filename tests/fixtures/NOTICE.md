# Third-party fixture

`oas-3.1-schema-2026-08-03.json` is the official JSON Schema of OpenAPI 3.1 documents, published by
the OpenAPI Initiative. It is stored verbatim and used only by the tests, which validate the generated
document against it (`[API-01]` acceptance).

- **Source:** <https://spec.openapis.org/oas/3.1/schema/2026-08-03> (`$id` of the same value), the latest
  iteration listed in [OAI/spec.openapis.org](https://github.com/OAI/spec.openapis.org/tree/main/oas/3.1/schema)
  when it was fetched.
- **Fetched:** 2026-10-09.
- **SHA-256:** `59f106413cb48c31299f96f024c938d3628aed6cd02cd14bcfb2fcaae7a130b6` (the tests check it).
- **License:** Apache License 2.0, © the OpenAPI Initiative, a Linux Foundation Collaborative Project
  (<https://www.apache.org/licenses/LICENSE-2.0>).

**How the tests read it.** `opis/json-schema` 2.6 does not resolve the schema's four `$dynamicRef: "#meta"`
references to the `$dynamicAnchor: "meta"` declared in `$defs/schema`. The tests therefore rewrite them,
in memory, to `$ref: "#/$defs/schema"` before validating. With this schema as the entry point that
anchor is the only one in scope, so the rewrite does not change what the schema accepts. The tests check
that precondition (one `$dynamicAnchor`, at `$defs/schema`) and the file's hash first, and they show the
validator rejecting invalid documents too.
