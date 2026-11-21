# Pre-mortem - NovaWebhook for MediaWiki 1.46

**verdict:** green after incorporating the probes and gates below; implementation remains unstarted

### Risks

- [high] integration - A signature probe can pass while core service construction, test configuration generation, or hook dispatch fails; probe: phase 0 runs one real edit through the service-wired handler and captured HTTP double, then the existing suite
- [high] premise - Skipped tests and direct hook dispatch can conceal missing API/web notifications or duplicates; probe: phase 1 replaces all seven skipped tests, performs real visibility changes, and records per-operation delivery counts before optimization
- [high] dependency - The extension declares PHPUnit 12 while core runs 9.6, and core now generates its configuration; probe: record resolved dependencies and discovered test list in phase 0, identify dependency ownership, and stop if the intended suite is not actually executed
- [medium] dependency - Moving core branches and Composer updates can change findings between planning and execution; probe: record core SHA and resolved dependencies in phase 0, rescan changed interfaces when refreshing the snapshot
- [medium] performance - Bounded output length does not bound full file-history queries or duplicate revision reads; probe: phase 2 verifies a history limit and revision lookup count while checking payload parity
- [medium] scope - The authorized dual-format architecture can grow into asynchronous delivery or a domain event redesign; probe: keep format work in phase 3, retain legacy hooks and synchronous transport, and defer unrelated execution models and formatting
- [medium] expertise - Domain events expose different causes and transactional timing, and adopting them can create extra notifications; probe: phase 2 compares a single visibility event against phase 1's source/test matrix and records a follow-up recommendation without registering both sources
- [medium] premise - Changing timestamp arguments may appear to fix event time while the transport ignores them; probe: phase 2 maps event-time sources and phase 3 asserts their actual UTC representation in emitted JSON
- [medium] operational - A supposedly local verification could send real notifications or be mistaken for a release; probe: phase 0 proves HttpRequestFactory replacement, all phases prohibit Discord access, and phase 5 separates verified/committed state from pushed/published/deployed state
- [low] operational - /tmp references disappear and remote release branches remain behind locally synchronized branches; probe: retain exact source SHAs and public source links, record local branch state, and require explicit publication scope before a later push
- [high] integration - Independent Discord message and JSON assembly could drift or repeat expensive core lookups; probe: phase 3 first fans one real edit out to two captured destinations from the same model, then demonstrates template changes leave JSON fixtures unchanged and renderers perform no lookups
- [high] premise - A generic serializer could expose hidden content, raw objects, secrets, or exception details beyond existing policy; probe: phase 3 uses an explicit metadata projection, tests hidden-content cases and both exception eligibility gates, and rejects object serialization
- [high] integration - URL-only routing can discard the selected format or duplicate mixed destinations; probe: phase 3 routes validated endpoint specifications and tests missing/invalid formats, exclusions, fan-out counts, and failure isolation
- [high] dependency - Config defaults can mask legacy explicit settings during the rebrand or combine aliases into duplicate notifications; probe: phase 4 validates explicit-setting detection and legacy/canonical/conflicting/default/privacy fixtures before removing old registrations
- [medium] operational - Renaming the extension identity alone can leave MediaWiki loading, Docker, or package paths broken; probe: phase 4 tests a fresh NovaWebhook install and documented migration, inventories intentional old names, and validates package inputs without publishing
- [medium] scope - Schema v1 and migration promises can outlive this implementation; probe: phase 3 defines field/nullability/time fixtures and phase 4 documents the transition window and precedence, with the final version reconsidered in phase 5

### Walking-skeleton check

Phase 0 exercises a real page save, core extension loading and service wiring, configuration routing, payload formatting, and the in-memory HTTP boundary on REL1_46. It records discovery, totals, and failures. Phase 3 repeats the slice with simultaneous Discord and JSON destinations before converting all handlers. Phase 4 repeats it under the new identity and legacy configuration migration. A declaration-only probe is insufficient.

### Phase-order revisions

| original | proposed | reason |
| --- | --- | --- |
| Modernize while changing the runtime | Establish runtime and actual hook coverage before modernization | Separates baseline failures from behavior changes |
| Convert notifications to domain events during upgrade | Preserve hooks and evaluate a bounded event candidate afterward | Avoids conflating namespace cleanup with cause/timing changes |
| Final release checks after modernization | Add structured formats, then rebrand, then run final release checks as phase 5 | Establish payload parity before renaming identifiers and verify the final product |

### Definition-of-done additions

- Phase 0: identify actual PHPUnit dependency owner, prove suite discovery and HTTP interception, and stop if the edit slice fails
- Phase 1: real operations and delivery counts for every registered hook, with API/web differences exposed rather than hidden behind manual dispatch
- Phase 2: observable query/lookup improvement and payload parity; map event-time sources for structured output and defer domain event redesigns
- Phase 3: shared-model fan-out slice, schema fixtures, legacy Discord parity, metadata projection, exception eligibility, and per-destination failure isolation
- Phase 4: fresh-load and configuration migration fixtures, explicit-setting precedence, stable schema/routing keys, and no duplicate registrations
- Phase 5: exact check results and coverage gaps for the rebranded dual-format extension; no release-ready claim while required verification or affected hook coverage remains incomplete

These revisions are incorporated into plan.md. The 2026-11-21 follow-up explicitly authorizes phases for dual formats using shared event data and the NovaWebhook rebrand. Schema projection, compatibility aliases, migration precedence, and version policy are proposed implementation details; no probes or phases are claimed executed.
