# Changelog

## 1.1.0 - 2026-11-06

### Fixed
- `isActive()` no longer reports a company as dissolved when ARES is unreachable or answers with a server error. It returns `false` only for a malformed, unknown or dissolved IČO and throws `AresException` for connection and API failures.
- The "not found" message now contains the IČO instead of the request path.
- A non-JSON error body (for example an HTML 502 page) now ends in `AresException` instead of a `JsonException`.

### Added
- `AresClient::isValidIco()` verifies the IČO check digit.
- Offline test suite (Nette Tester) with a mocked HTTP layer.
