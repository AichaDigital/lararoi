# Changelog

All notable changes to `lararoi` will be documented in this file.

## [v0.2.0] - 2025-11-16

### Added
- **Configurable caching system**: Enable/disable cache via `CACHE_ENABLED`
- **Cache status tracking**: Response includes `cache_status` field ('fresh', 'cached', 'refreshed')
- **Custom model support**: Full support for custom models with custom primary keys (UUID, ULID, etc.)
- **Custom foreign keys**: Configure foreign key names for relationships
- **Agnostic mode**: Disable cache for minimal database footprint
- Comprehensive test coverage for VerifyVatCommand (0% → 48.2%)
- Additional cache behavior tests

### Changed
- **Cache configuration restructured**: Moved from `cache_ttl` to `cache.enabled` and `cache.ttl`
- **Model configuration enhanced**: Support for `class`, `primary_key`, and `foreign_key`
- Updated all documentation (configuration.md, usage.md) with new features
- Environment variables updated in `.env.example`

### Fixed
- PHPStan nullsafe operator error in IsvatProvider
- PHP Insights code quality issues (empty() usage, parentheses)
- Test failures in VerifyVatCommandTest

### Documentation
- Added "Advanced Usage" section with agnostic mode examples
- Added complete guide for custom models with relationships
- Updated environment variables documentation
- Added cache behavior documentation

## [v0.1.0] - 2025-01-XX

### Initial Release
- VAT number verification via multiple providers
- Support for VIES (REST/SOAP), isvat.eu, vatlayer, viesapi
- Dual-layer caching (memory + database)
- Automatic provider fallback
- Robust error handling
- Development commands for testing
