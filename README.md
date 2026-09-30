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

## 🧭 Architectural boundary (`mago guard`)

`Waffle\Commons\OpenApi` depends only on `waffle-commons/contracts` (plus `waffle-commons/utils`
for shared primitives) and the PSR interfaces — never on a sibling component's concrete classes. Every
interface it implements lands in `waffle-commons/contracts` first.

## 🧵 Worker safety

Services are resident in FrankenPHP worker mode: no mutable state may survive a request. Anything that
holds per-request state implements `ResettableInterface` directly; `composer igor` must report 0 KO.

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
