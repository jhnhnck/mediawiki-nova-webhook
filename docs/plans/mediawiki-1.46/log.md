# Log - NovaDiscord for MediaWiki 1.46

## 2026-11-21 - plan creation and branch preparation

- Read AGENTS.md and applied plan-create, approach-select, pre-mortem, feature-completion, docker-commands, and commit-style as relevant to planning, environment inspection, and committing
- Origin fetch showed trunk and REL1_44 at c714dc9; local trunk was nine commits ahead at f5da866
- Created local REL1_44 tracking origin/REL1_44 and fast-forwarded it to trunk; created REL1_46 from the equal baseline; no push
- Cloned MediaWiki REL1_46 into /tmp/nova-mediawiki-rel146 and fetched shallow REL1_44/REL1_45 refs; stopped the stalled initial Gerrit clone
- Inspected 17 hook contracts across three snapshots, call paths, runtime tooling, skipped tests, and domain event alternatives; recorded exact core SHAs in compatibility.md
- Required the 17 core interfaces and all six extension handlers in a temporary PHP probe; declaration loading passed
- Docker daemon inspection succeeded with escalation; no Composer installation or integration suite execution during planning
- Selected existing hooks plus bounded improvements; reviewed the draft in a separate pre-mortem pass and incorporated discovery, delivery-count, dependency, and timestamp probes
- Application code, dependencies, README, and version requirements remain unchanged; implementation phases have not started

## 2026-11-21 - structured formats and NovaWebhook scope revision

- User requested a phase for Discord/structured JSON destinations, selected Discord rendering from shared event data, and requested a separate NovaWebhook rebrand phase
- Added phase 3 for structured events and dual formats, phase 4 for the rebrand and configuration migration, and moved final release readiness to phase 5
- Defined a JSON-compatible shared model with serialization at delivery, default Discord format for existing entries, metadata-only JSON v1 projection, and preserved hook routing and exception safeguards
- Proposed canonical NovaWebhook configuration with legacy aliases for one transition release and explicit canonical-setting precedence; phase 4 must verify MediaWiki loading/configuration behavior before promising compatibility
- Updated phase 2 to retain timestamp plumbing for phase 3's observable event timestamps; the earlier provisional 3.2.0 release version now requires reassessment against the rebrand
- Reviewed new serializer, privacy, endpoint routing, configuration precedence, and loading/path risks in a separate pre-mortem pass and incorporated the probes into phase completion criteria
- Preserved the plan directory, historical compatibility findings, and existing phase states; application code remains unchanged

Phase starts, completion evidence, retrospectives, and plan revisions append here. Phase state is maintained only in plan.md's final status table.

## 2026-11-21 - phase 0 started

Execution authorized for all remaining phases on REL1_46 at bda6659. Scope: runnable 1.46 baseline, canonical imports, core-owned PHPUnit dependencies, handler loading and real save interception. Baseline has seven skipped tests; runtime failures have not yet been measured.

## 2026-11-21 - phase 0 verification and review

- Core d88c752b54c6052fb4cce261b140c88589420eed, PHP 8.4.28, core PHPUnit 9.6.34; all six handlers resolve through HookContainer and injected services
- Initial image suite: 39 tests / 62 assertions / 7 skips; current mounted suite with registration/request assertions: 41 tests / 84 assertions / 7 skips, exit 0
- Restricted runner discovery to tests/phpunit: mounting the checkout otherwise traversed protected host Git objects; also avoids discovering vendor tests
- Enabled pipeline error propagation and replaced the tr/head password pipeline to avoid its expected SIGPIPE; runner reports core/runtime/dependency versions and accepts PHPUnit arguments
- Extension dev PHPUnit constraint is ^9.6; core owns integration execution, independent extension install supplies lint/Phan tooling (resolved local PHPUnit 9.6.39 is not the core runner)
- PHP syntax and zsh syntax passed. composer check fails on 53 existing codesniffer errors; an archived bda6659 checkout reproduced the same 53 errors with the same tools. New import-order warnings were corrected; the new wiring test passes codesniffer
- Reviewed phase diff against bda6659 for requirements, import aliases, runner failure propagation, service resolution, and HTTP interception; no remaining phase-specific findings. Existing lint debt is tracked for final readiness, not silently counted as passing
- Runtime compatibility slice is verified; remaining seven skipped hook tests belong to phase 1. No Discord requests, push, publication, or deployment

## 2026-11-21 - phase 1 started

Parent 314a490 on REL1_46. Replace skipped operations and manual dispatch with core services/API operations, verifying delivery counts, filters, and exception gates. Baseline: 41 tests / 84 assertions / 7 skips; pre-existing lint B001 remains.

## 2026-11-21 - phase 1 verification and review

- Full unfiltered 1.46 suite: 50 tests / 143 assertions / zero skips, exit 0 (/tmp/nova-phase1-final.log)
- All seven skipped tests now use real operations: WikiImporter, MergeHistory, UploadFromFile, delete/undelete APIs, RenameuserSQL, and UserGroupAssignmentService. Revision visibility uses RevisionDeleter on a non-current revision; registration uses AuthManager autocreation
- File fixtures use distinct per-test-class names and changed upload bytes. Core correctly rejects identical reuploads and hiding current revision text; fixtures now exercise valid operations instead of bypassing those checks
- Delivery counts: merge produces merge plus redirect edit (2); whole file deletion produces page delete only (1); old file version deletion produces file notification (1); restore produces page and file notifications (2); import produces import notification (1). Web SpecialUndelete and ApiUndelete both explicitly invoke FileUndeleteComplete after successful file restoration; API path executed, web caller checked in core source
- Automatic group changes stay silent. Regression tests cover bots, namespace filtering, empty destinations, excluded hooks, null/minor edits, exception opt-in, explicit routing, suppressed exceptions, deny-list, and repeated exception limiting
- Enabled CLI APCu in the test runner and proved the same exception emits once. Null saves report the expected edit-no-change warning while remaining OK
- Reviewed actual diff for test isolation, real operation coverage, actor context, valid core permissions, expected additional notifications, and HTTP interception. No phase-specific blockers remain; existing lint debt B001 remains assigned to phase 5
