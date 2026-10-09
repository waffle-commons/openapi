[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/openapi/require/php)](https://packagist.org/packages/waffle-commons/openapi)
[![PHP CI](https://github.com/waffle-commons/openapi/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/openapi/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/openapi/graph/badge.svg)](https://codecov.io/gh/waffle-commons/openapi)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/openapi/v)](https://packagist.org/packages/waffle-commons/openapi)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/openapi/v/unstable)](https://packagist.org/packages/waffle-commons/openapi)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/openapi.svg)](https://packagist.org/packages/waffle-commons/openapi)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/openapi)](https://github.com/waffle-commons/openapi/blob/main/LICENSE.md)

Waffle OpenApi Component
==========================

> **Release:** _unreleased_ &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **Status:** In development for `0.1.0-beta7` — roadmap `[API-01]` (RFC-016). Generation runs at build time (`openapi:generate`); nothing is added to the request hot path.

OpenAPI 3.1 document generation for the Waffle framework, built from #[Route] attributes and typed DTOs.

## 📦 Installation

```bash
composer require waffle-commons/openapi
```

## 🛠 Generating the document

Register the command in `bin/waffle` next to the console commands, with the booted router:

```php
use Waffle\Commons\OpenApi\Command\GenerateCommand;

$application->add(new GenerateCommand($router));
```

```bash
bin/waffle openapi:generate --output=public/openapi.json --title="Orders API" --api-version=1.4.0
bin/waffle openapi:generate --output=-   # to the standard output
```

The document (OpenAPI **3.1**) is written atomically — a temporary file renamed over the target — and
validates against the official OpenAPI 3.1 schema (the component's tests check it). Nothing runs on a
request: the generator reflects on the controllers at build time only.

## 🧩 What the document describes

The routes come from `RouterInterface::getRoutes()`, the metadata `route:list` and `route:compile` read.
For each route, the generator reflects on its controller method:

| Source | Becomes |
| :--- | :--- |
| `{name}` / `{name:constraint}` in the path | a required path parameter typed from the controller parameter of the same name (`int` → `integer`, `float` → `number`, `bool` → `boolean`, else `string`); a string parameter's constraint becomes its `pattern` |
| the first parameter typed with a `#[Dto]` class | the JSON request body; required unless the parameter is nullable or defaulted |
| return type `#[Dto]` | `200` with the JSON schema of the DTO |
| return type `array` / `string` | `200` JSON / `200` HTML (no schema) |
| return type `void` | `204` |
| a nullable return type | adds `204` |
| any other or no return type | a bare `200` |

Each HTTP method of a route is an operation; its `operationId` is the route name, suffixed with the
method when the route serves several, and numbered when two routes share a name. A route that accepts
any method is documented for GET, POST, PUT, PATCH and DELETE; a method OpenAPI 3.1 cannot describe is
left out, and a `HEAD` response carries no body.

**Schemas** come from `Waffle\Commons\Utils\Dto\Shape\DtoShapeReader`, the reader the serializer's
compiled normalizers use, so the document describes exactly what the serializer accepts and emits:

- a request body is the DTO's constructor parameters — required when they have no default and are not
  nullable, with their defaults;
- a response body is its readable properties, all present (`null` included); a computed one (a `get`
  hook) is `readOnly`;
- backed enums are `enum` schemas of their backing values; `#[ListOf]` arrays are `array` schemas;
  nullable fields add `null`.

DTOs and enums are shared under `components/schemas`, named after their short class name (the full
name, dotted, when two classes share it). A DTO whose request and response shapes differ, and that the
document uses on both sides, gets two components: `Name-Input` and `Name-Output`.

Not yet: `#[OA\*]` description overrides and the development-only Swagger UI route (`[API-01]`
follow-ups). Without them the document is valid but terse.

## 🧭 Architectural boundary (`mago guard`)

`Waffle\Commons\OpenApi` depends only on `waffle-commons/contracts`, `waffle-commons/utils` (the
DTO-shape reader) and the PSR interfaces — never on a sibling component's concrete classes. The routes
come through `RouterInterface`, and the command implements `Console\CommandInterface` (`[CARRY-04]`).

## 🧵 Worker safety

Generation is a build-time step: the generator, the schema catalog and the command keep no state between
calls (`final readonly` classes), and `composer igor` reports 0 KO.

## 🧪 Testing

```bash
composer mago    # formatter, linter, analyzer, guard — clean means zero output
composer tests   # PHPUnit 12.5, line coverage ≥ 95 %
composer igor    # worker-safety audit
```

## 📚 Documentation

Full documentation tree: [waffle-commons/documentation](https://github.com/waffle-commons/documentation)

## 📄 License

MIT — see [`LICENSE.md`](./LICENSE.md).
