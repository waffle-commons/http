# Changelog — waffle-commons/http

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta4] — 2026-06-13

**Theme: resource-leak resolution & upload safety.**

### Added
- `Factory\UploadedFilesNormalizer` — extracted the recursive `$_FILES` parsing into an isolated, fully-tested class (ARCH-06).
- `UploadedFile::moveTo()` now screens its destination through `Assert::safePath()`, rejecting directory-traversal / null-byte targets (SEC-05).

### Changed
- `Stream` resource-ownership model (`$ownsResource`): `detach()` / `close()` release the underlying descriptor cleanly, with no double-free (STB-01).
- Added an optional dev-only connection-tracer hook to `Stream` (DIAG-03).
- Worker-safety migration to igor-php 0.7 (`#[WorkerSafe]`).

## [0.1.0-beta3] — 2026-06-07

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Changed
- Header normalisation and URI parsing refactored across the message classes for clarity and performance.
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

## [0.1.0-beta2.1] — 2026-05-30

### Changed
- Lockstep re-tag of `0.1.0-beta2` (umbrella housekeeping patch) — no source changes in this component.

## [0.1.0-beta2] — 2026-05-29

### Changed
- Lockstep version bump only. No behavioural changes since `0.1.0-beta1`.
- `composer.lock` refreshed to align with the ecosystem-wide dependency wave.

## [0.1.0-beta1]

See the umbrella [CHANGELOG](../CHANGELOG.md#010-beta1) for the full Beta-1 narrative — PSR-7/17 implementation, `GlobalsFactory`, `ResponseEmitter`, trusted-host hardening.
