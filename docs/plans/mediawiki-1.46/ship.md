# NovaWebhook 4.0.0 readiness

**verdict:** ship

This is a local code-readiness verdict for REL1_46. It does not indicate a remote push, merge, package publication, consuming-wiki upgrade, or deployment.

## delivered

- MediaWiki >=1.46.0 and PHP >=8.3.0 requirements, canonical hook interfaces, and a working 1.46 integration environment
- Shared event data for all 17 notifications, Discord rendering from that data, and per-endpoint discord/json selection with documented JSON schema v1
- Preserved Discord payloads, filters, explicit exception routing, opt-in, deny-list, rate limiting, quiet exception handling, and destination failure isolation
- NovaWebhook identity, namespaces, service names, message keys, logger, development paths, package name, and installation documentation
- Legacy Discord-prefixed settings for the transition release, with explicit canonical precedence and no endpoint-list merging
- Bounded file history retrieval, shared parent revision lookup, early routing checks, direct Title construction, and operation timestamps for deletion/restoration
- Real-operation coverage replacing the seven skipped tests, plus migration, failure, privacy, filtering, and selected-revision restoration regressions

## evidence

| check | result |
| --- | --- |
| Fresh final image: `docker compose --file .dev/docker-compose.yml run --build --quiet-build --rm --remove-orphans job-tests zsh ./entry.zsh --all` | Pass; 73 tests, 523 assertions, zero skips |
| Final canonical settings, current source mounted into the same environment | Pass; 73 tests, 523 assertions, zero skips |
| Final legacy-only installation settings, `NOVA_TEST_LEGACY_CONFIG=1` | Pass; 73 tests, 523 assertions, zero skips |
| `composer check` in PHP 8.4 test container | Pass; 40 PHP files linted, codesniffer and minus-x clean |
| `composer phan` with MW_INSTALL_PATH pointing to core | Pass; PHP 8.3 target and supported polyfill parser |
| Manifest, 17 hook registrations and six handler services, ten config definitions, localization JSON, message keys, and local doc links | Pass |
| zsh syntax, full diff whitespace, rename inventory, and phase/status consistency | Pass |

Runtime: PHP 8.4.28, MediaWiki core `d88c752b54c6052fb4cce261b140c88589420eed` (REL1_46, 1.46.3), core PHPUnit 9.6.34. Integration uses the in-memory HTTP double; no Discord webhook was contacted. Test logs were captured in `/tmp/nova-release-image-tests.log`, `/tmp/nova-final-canonical.log`, `/tmp/nova-final-legacy.log`, `/tmp/nova-final-check.log`, and `/tmp/nova-final-phan.log`; this document preserves their results if temporary files disappear.

## blockers

None for local code readiness. The pre-existing lint failure B001 was resolved separately in `824392c` without suppressions. Release metadata selects 4.0.0 because the loading identity and PHP/service names change; configuration compatibility is documented separately.

## limits and deferred work

- Runtime tests used PHP 8.4; PHP 8.3 compatibility was statically checked rather than executed on a separate runtime
- File restoration's API path was executed; its web caller was checked in core source, without browser automation
- Domain event migration and asynchronous delivery/retries remain outside this release; existing callback-context actor limitations are retained where core supplies no performer
- The existing backlog retains Discord undelete count presentation, further formatting-character review, and a README example image; none blocks this release
- The hosted repository URL remains unchanged. Operators must install/load NovaWebhook and follow the migration guide; old PHP class/service/loading aliases are not supplied

## branch state

REL1_44 and local trunk remain equal at `f5da86625a8d2f8ac0fe52549979e3e19643d1cc`. Implementation and plan closure belong to REL1_46. Remote refs have not been pushed. Content commits and phase closure are recorded in plan.md and log.md; deployment requires its own authorization.
