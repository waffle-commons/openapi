# Changelog — waffle-commons/openapi

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta7] — 2026-11

**Theme: first release — OpenAPI 3.1 generation from routes and DTOs (API-01).**

### Added
- Initial scaffold from `waffle-commons/component-template`: CI pipeline (Mago, Igor, PHPUnit,
  TruffleHog, SBOM, Psalm taint analysis), Dependabot, Mago zero-baseline configuration.
- **OpenAPI 3.1 generation (`[API-01]`):**
  - `OpenApiGenerator` — builds the document from `RouterInterface::getRoutes()` and build-time reflection
    on each controller method: paths and typed path parameters (a string parameter's route constraint is
    its `pattern`), the first `#[Dto]` parameter as the JSON request body, and responses that follow the
    kernel's conversion of the returned value (`#[Dto]` → `200` with its schema, `array` → `200` JSON,
    `string` → `200` HTML, `void` → `204`, a nullable return adds `204`). `encode()` writes pretty JSON.
  - `Schema\SchemaCatalog` and `Schema\ComponentNames` — DTO and backed-enum schemas from the `utils`
    `DtoShapeReader`, shared under `components/schemas`; a DTO whose request and response shapes differ
    and that the document uses on both sides gets `Name-Input` and `Name-Output`.
  - `Command\GenerateCommand` — `openapi:generate [--output=<path>|-] [--title=…] [--api-version=…]`, a
    `Console\CommandInterface` registered in `bin/waffle`; it writes the document atomically.
  - The tests validate the generated document against the official OpenAPI 3.1 schema (iteration
    2026-08-03, stored verbatim in `tests/fixtures/` with its notice) and pin it with a golden file.

### Changed
- Requires `waffle-commons/utils` (the DTO-shape reader); the `mago guard` perimeter permits
  `Waffle\Commons\Utils\**`. `opis/json-schema` ^2.6 is a development dependency.
