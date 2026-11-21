# Pre-mortem - NovaDiscord for MediaWiki 1.46

**verdict:** green after incorporating the probes and gates below; implementation remains unstarted

### Risks

- [high] integration - A signature probe can pass while core service construction, test configuration generation, or hook dispatch fails; probe: phase 0 runs one real edit through the service-wired handler and captured HTTP double, then the existing suite
- [high] premise - Skipped tests and direct hook dispatch can conceal missing API/web notifications or duplicates; probe: phase 1 replaces all seven skipped tests, performs real visibility changes, and records per-operation delivery counts before optimization
- [high] dependency - The extension declares PHPUnit 12 while core runs 9.6, and core now generates its configuration; probe: record resolved dependencies and discovered test list in phase 0, identify dependency ownership, and stop if the intended suite is not actually executed
- [medium] dependency - Moving core branches and Composer updates can change findings between planning and execution; probe: record core SHA and resolved dependencies in phase 0, rescan changed interfaces when refreshing the snapshot
- [medium] performance - Bounded output length does not bound full file-history queries or duplicate revision reads; probe: phase 2 verifies a history limit and revision lookup count while checking payload parity
- [medium] scope - A compatibility task can grow into a transport/domain event redesign or a lint sweep; probe: retain legacy hooks, preserve payload/routing keys, and defer changes requiring a new execution model or unrelated formatting
- [medium] expertise - Domain events expose different causes and transactional timing, and adopting them can create extra notifications; probe: phase 2 compares a single visibility event against phase 1's source/test matrix and records a follow-up recommendation without registering both sources
- [medium] premise - Changing timestamp arguments may appear to fix event time while the transport ignores them; probe: phase 2 traces timestamp consumption and records a deliberate API decision before changing event-time behavior
- [medium] operational - A supposedly local verification could send real notifications or be mistaken for a release; probe: phase 0 proves HttpRequestFactory replacement, all phases prohibit Discord access, and phase 3 separates verified/committed state from pushed/published/deployed state
- [low] operational - /tmp references disappear and remote release branches remain behind locally synchronized branches; probe: retain exact source SHAs and public source links, record local branch state, and require explicit publication scope before a later push

### Walking-skeleton check

Phase 0 exercises a real page save, core extension loading and service wiring, configuration routing, payload formatting, and the in-memory HTTP boundary on REL1_46. It records discovery, totals, and failures. This is the minimum useful integration slice; a declaration-only probe is insufficient.

### Phase-order revisions

| original | proposed | reason |
| --- | --- | --- |
| Modernize while changing the runtime | Establish runtime and actual hook coverage before modernization | Separates baseline failures from behavior changes |
| Convert notifications to domain events during upgrade | Preserve hooks and evaluate a bounded event candidate afterward | Avoids conflating namespace cleanup with cause/timing changes |

### Definition-of-done additions

- Phase 0: identify actual PHPUnit dependency owner, prove suite discovery and HTTP interception, and stop if the edit slice fails
- Phase 1: real operations and delivery counts for every registered hook, with API/web differences exposed rather than hidden behind manual dispatch
- Phase 2: observable query/lookup improvement and payload parity; record the unused timestamp decision and defer redesigns
- Phase 3: exact check results and coverage gaps; no release-ready claim while required verification or affected hook coverage remains incomplete

These revisions are incorporated into plan.md. They are planning decisions, not a claim of user approval or executed probes.
