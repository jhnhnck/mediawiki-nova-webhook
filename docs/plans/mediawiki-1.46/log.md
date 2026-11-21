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

## 2026-11-21 - phase 2 started

Parent 9981de3 on REL1_46. Preserve 50-test behavior baseline while reducing page construction, file history retrieval, and parent lookups; map event timestamp sources for structured output.

## 2026-11-21 - phase 2 verification and review

- Full suite remains 50 tests / 143 assertions / zero skips, exit 0 (/tmp/nova-phase2-tests.log); existing exact Discord payload assertions remain unchanged
- Page deletion/restoration now construct Title directly from PageIdentity. Upload reversion detection calls getHistory(1); inspected core LocalFile applies the limit to the oldimage SQL query
- Edit handling obtains the parent once and passes it to both revision metadata and diff helpers; neither formatting helper performs another lookup. Routing rejects hooks without eligible endpoints before diff/history work
- Timestamp source map for phase 3: saves/moves use revision event time; deletion/restoration use the supplied ManualLogEntry timestamp; uploads use file timestamp; block uses block timestamp; unblock/file-version deletion use current operation time because their hook supplies no log/event timestamp. Import, merge, visibility, protection, rename, and manual groups likewise require hook-time fallback
- Kept existing hooks. PageHistoryVisibilityChanged offers explicit performer/log timestamp but adoption would require separate cause/timing/suppression parity work; no simultaneous hook/event registration. Kept upload hook rather than adding FileUpload
- Reviewed actual phase diff for lookup reuse, missing-parent behavior, namespace filtering, title identity, and HTTP routing. No phase-specific defects; B001 lint debt remains for final readiness

## 2026-11-21 - phase 3 started

Parent 9ceec2d on REL1_46. Shared event model, Discord renderer, metadata-only JSON v1, validated per-destination format, existing message parity, safe mixed-format delivery. Baseline 50 tests / 143 assertions / zero skips.

## 2026-11-21 - phase 3 verification and review

- Shared WebhookEvent data now drives DiscordRenderer and JSON v1 projection across all 17 handlers. Event values reject framework objects; raw diff content stays internal and JSON-only edits avoid diff generation
- Full suite: 60 tests / 474 assertions / zero skips (/tmp/nova-phase3-final.log). Existing Discord payload fixtures still pass; operation tests now capture both destinations and assert each hook's JSON field map
- Real edit fan-out verifies actor, revision ID, raw Unicode summary, destination, schema version, UTC time, and no raw revision body. Deletion verifies log operation time rather than old revision time
- HTTP 503 and thrown transport failures do not block the next destination. Invalid formats (including explicit null), malformed/insecure URLs, exclusions, invalid UTF-8, and JSON payloads larger than Discord's limit are covered. Serializer does not mutate event metadata
- Core supplies null fileVersions for an all-version API restore; JSON v1 preserves null instead of coercing it to an empty list. This was found by the real-operation test
- Config routing now preserves format metadata. Format defaults to discord only when absent, and invalid entries log no endpoint URLs. Exceptions retain explicit routing, opt-in, deny-list, suppressed-error checks, rate limit, and quiet failure behavior for both serializers
- Phan with --allow-polyfill-parser passed after nullable uploader guards and local data narrowing; default command cannot run without php-ast in the image. Final tooling will make the supported fallback explicit. Syntax passed; codesniffer debt remains B001 for separate housekeeping
- Reviewed phase diff against 9ceec2d: collection/rendering boundaries, timestamp sources, metadata privacy, template parity, error isolation, and default compatibility. Added docs/webhook-events.md as the public v1 field/time contract

## 2026-11-21 - phase 4 started

Parent f371d8b on REL1_46. Canonical NovaWebhook identity and endpoints configuration, complete namespace/service/message/test/tooling rename, explicit legacy setting precedence and documented load-path migration. Baseline: 60 tests / 474 assertions / zero skips.

## 2026-11-21 - phase 4 verification and review

- Canonical NovaWebhook extension identity, PHP namespace, services, message keys, logger channel, Compose project, test domain, loading paths, and package name now agree. Hosted Git URL, historical notes, original checkout path, and plan directory remain unchanged
- Configuration names use NovaWebhook, with NovaWebhookEndpoints replacing DiscordWebhooks. Legacy Discord-prefixed settings are accepted for one transition release; explicit canonical false/null/empty arrays win and endpoint aliases never merge
- MediaWiki exports manifest config defaults before services are built, obscuring explicit-setting presence. To preserve exact precedence without sentinel values or handler globals, defaults/descriptions now live in attributes.NovaWebhook.ConfigDefaults and are normalized into ServiceOptions only after main-config presence checks. MediaWiki prefixes the runtime attribute as NovaWebhookConfigDefaults
- Fresh canonical installation: 69 tests / 499 assertions / zero skips (/tmp/nova-phase4-final.log). Legacy-only LocalSettings installation with the additional fresh-config test: 71 tests / 504 assertions / zero skips (/tmp/nova-phase4-legacy.log). The actual service factory reads legacy globals through Config, not just HashConfig fixtures
- Migration fixtures cover defaults, canonical-only, legacy-only, conflicting explicit values, canonical null hooks, duplicate endpoint aliases, and exception privacy precedence. Diagnostic capture proves no setting values/endpoint secrets are logged
- Phan parser-fallback run passed (/tmp/nova-phase4-phan.log). Reviewed full rename diff against f371d8b, manifest registrations, source reference inventory, package.conf/script resolution, canonical and legacy loading, schema/routing stability, and migration docs. No publish script was run
- Corrected attribute prefixing discovered by the fresh install. Runner copies LocalSettings.new instead of moving it so read-only fixture mounts work
- Intentional old-name references: hosted repository URLs, migration tests/docs, compatibility mapping, historical notes/planning evidence, AGENTS deployment reference, and the publish script's existing sibling checkout-path comment. No duplicate registration or old PHP/service aliases are promised
- Remaining lint debt stays B001 and will be resolved in a separate housekeeping commit during phase 5. Public loading-name change warrants reassessing the previous minor-version assumption

## 2026-11-21 - phase 5 started

Parent 1ef2ba4 on REL1_46. Resolve B001 separately, finalize public requirements/schema/migration docs and backlog, select 4.0.0 for the loading/namespace breaking change, and execute final canonical/legacy integration plus check/Phan gates.

## 2026-11-21 - phase 5 lint housekeeping verification

composer check now passes: PHP parallel lint (40 files), PHP_CodeSniffer (zero errors/warnings), and minus-x. Corrected inherited hook documentation, imports/whitespace, typed test-double property, and wrapped exact wire-payload fixtures. Canonical integration suite remains 71 tests / 504 assertions / zero skips (/tmp/nova-lint-tests.log). The format-only diff was reviewed separately from release metadata and documentation. B001 is resolved with this verification; preserve a separate housekeeping commit.
