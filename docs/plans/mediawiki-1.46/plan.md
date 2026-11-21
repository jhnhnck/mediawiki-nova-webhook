# NovaWebhook for MediaWiki 1.46

## goals

- Run NovaDiscord's registered notifications on MediaWiki REL1_46 with verified payloads and no unintended duplicate delivery
- Replace deprecated hook interface imports, establish real coverage for the seven skipped hook tests, and preserve routing and exception safeguards
- Reduce unnecessary page construction, revision lookups, and file history queries where source evidence supports the change
- Build one structured event model per notification and render Discord messages from that data; offer a versioned JSON payload per destination
- Rebrand the extension to NovaWebhook with a documented migration from NovaDiscord names and configuration
- Finish with consistent requirements, development commands, documentation, and recorded verification evidence on REL1_46

## non-goals

- Deploying the extension, publishing packages, sending Discord webhooks, or upgrading a consuming wiki's database
- Maintaining 1.44 compatibility on this branch; REL1_44 retains the synchronized baseline
- A wholesale domain event conversion, asynchronous delivery, retries, new notification categories, or a generic log event feed
- Updating every development dependency or fixing unrelated lint findings
- Renaming the hosted Git repository, moving published artifacts, adding webhook authentication protocols, or adding formats beyond Discord and JSON

## constraints

- Plan directory: `docs/plans/mediawiki-1.46/`; phase details remain in this file
- Implementation belongs on REL1_46; REL1_44 and trunk were equal at `f5da86625a8d2f8ac0fe52549979e3e19643d1cc` before this planning commit
- Preserve the existing plan path and historical compatibility report through the NovaWebhook rename
- Branch synchronization is local. No remote refs were pushed; remote trunk and REL1_44 were both `c714dc9` when fetched
- Follow AGENTS.md, constructor injection, NovaDiscordConfig routing, HTTPS validation, payload encoding, bot/minor/null/namespace filters, file-save handling, and exception privacy/rate limiting
- Use the in-memory HTTP double. Interacting with Discord requires separate user authorization
- Core source snapshots and the full hook inventory are recorded in [compatibility.md](compatibility.md); refresh that evidence if core revisions change
- PHP 8.4 remains the default container runtime; target core requires PHP >=8.3.0. Do not infer minimum-runtime coverage from a PHP 8.4 run
- Integration tests use core's Composer/PHPUnit environment. The extension's standalone PHPUnit declaration and empty bootstrap do not establish an independent test environment
- Commit complete phase units under standing authorization. Merge, push, package publication, and deployment are separate from local verification
- Existing webhook entries without a format continue to use Discord. Preserve public hook routing keys, existing Discord payloads, and exception safeguards for both formats
- Use JSON-compatible scalars and arrays for event data; serialize only at the HTTP boundary. Discord renderers consume the event model without reading core services or decoding serialized JSON

## accepted risks

Core release branches and unpinned build dependencies move; record exact versions at execution. The successful interface load probe does not establish runtime behavior. Seven skipped tests and one manually dispatched visibility hook leave coverage gaps; phase 1 retires those before modernization. Synchronous delivery remains an accepted existing limitation. Domain event conversion stays deferred because equivalent coverage, timing, and routing have not been demonstrated. The user selected shared event data with Discord rendering and requested the NovaWebhook rebrand. JSON schema details, legacy-name migration behavior, and release version remain planning assumptions. The [pre-mortem](pre-mortem.md) records their probes and pivot criteria.

---

### phase 0 - 1.46 compatibility baseline

**scope:** Produce a runnable 1.46 branch with the existing event model and an edit-to-captured-webhook integration slice. Change `.dev/Dockerfile` arguments to `1.46` and `REL1_46`, set the manifest MediaWiki requirement to `>= 1.46.0`, and update the seven interface imports listed in the compatibility report. Keep the extension version unchanged until final release preparation. Check `.dev/assets/entry.zsh` against core's current `composer phpunit` configuration generation; its `phpunit:entrypoint` alias still exists, so a runner rename alone is not a compatibility fix. Reconcile the extension's `^12` PHPUnit declaration with core's actual 9.6 runner and document which environment owns dependencies. Prove test discovery and HTTP interception before relying on a suite result.

**dod:** Record core SHA, PHP version, Composer resolution, test discovery, and unfiltered test totals. Run an existing page creation/edit test through the real save service and verify the captured JSON, URL, and delivery count without network requests. Instantiate all six handlers through the service container. Run the existing integration suite, separating failures, skips, and deprecation output. Preserve pre-existing failures explicitly; fix blockers to this slice before closing. If core bootstrap, PHPUnit configuration, or the HTTP double cannot work, revise this phase before broader migration work.

**merge gate:** Clean task scope on REL1_46 descended from the synchronized baseline; Docker Compose and daemon available with required sandbox access; no Discord access

---

### phase 1 - hook behavior and coverage

**scope:** Verify all 17 registered hooks against real core operations. Replace the seven skipped tests: AfterImportPage, ArticleMergeComplete, UploadComplete, FileDeleteComplete, FileUndeleteComplete, RenameUserComplete, and UserGroupsChanged. Use core's UserGroupAssignmentService for manual group changes and verify automatic changes with performer `false` remain silent. Replace or supplement the manually dispatched visibility test with the real revision deletion service. Exercise upload/reupload, file revision deletion/restoration, page deletion/restoration, rename, move, protection, import, and merge. Inspect web and API callers where they differ, especially file restoration. Retain deliberate silence for NS_FILE page saves, routing exclusions, null/minor edits, and automatic group changes. Check exception opt-in, explicit routing, deny-list, rate limiting, and quiet failure handling.

**dod:** The inventory maps every hook to an executed test and core call path; skips are not counted as passing coverage. Assert delivery counts as well as relevant content. Record combined page/file notifications for deletion and restoration and distinguish separate intended events from duplicate delivery. Preserve the page-save slice and run the unfiltered full suite. If a core path omits a hook, emits it twice unexpectedly, or lacks the claimed actor, document the exact path and revise notification behavior before closing; do not paper over it with a direct hook dispatch. Existing exception and filtering safeguards remain verified.

**merge gate:** Phase 0 verified; working HTTP interception and core service wiring; no Discord access

---

### phase 2 - bounded modernization

**scope:** Apply small improvements behind phase 1's behavioral coverage. Build a Title directly from PageIdentity in deletion/restoration handlers instead of constructing a WikiPage solely for its Title; retain WikiPageFactory only where still needed. Limit upload history to one old version when testing whether history exists. Reuse parent-revision data across size and diff formatting where straightforward. Resolve routing before expensive diff/history work while preserving exception explicit-only behavior. Remove obsolete comments and aliases only in touched code. Review deletion/restoration timestamps against ManualLogEntry: the current code uses content revision time, and the transport currently ignores the timestamp argument. Record the correct event-time sources for phase 3's observable timestamp field; retain necessary plumbing until that phase. Preserve the payload format in this phase.

Evaluate one bounded domain event candidate on paper against the completed tests: PageHistoryVisibilityChanged provides actor and log timestamp data without RequestContext. Keep the legacy hook as the selected implementation for this plan; a later event migration must preserve the `ArticleRevisionVisibilitySet` configuration key, active-revision coverage, suppression policy, and exactly one delivery. FileUpload is likewise an alternative to UploadComplete, not a second simultaneous notification source.

**dod:** New assertions demonstrate preserved payloads, counts, routing, and filters; a query limit or lookup count demonstrates each claimed performance improvement. The page-save slice and full suite still pass. Source review confirms no new service locator calls, narrowed untyped hook parameters, exception feedback loops, or hidden reads of globals. Record event-time sources and the domain event comparison for phase 3. Stop or defer any optimization that changes coverage or requires a larger architecture redesign; do not broaden this phase to make the optimization fit.

**merge gate:** Phase 1 verified with real-operation coverage for affected handlers; preserve public hook routing keys and existing payload format; no Discord access

---

### phase 3 - structured events and dual webhook formats

**scope:** Separate event collection, Discord rendering, and HTTP delivery across all 17 hooks. Establish a JSON-compatible event model with a versioned envelope: schema version, stable event type, original hook name, UTC event timestamp, actor, optional page identity/URL, and event-specific data. Define explicit nullability and timestamp fallback rules; use event/log time where supplied, not content revision time for deletion/restoration. Hook handlers gather data once. Discord renderers derive the current localized messages, links, summaries, and bounded diff blocks from that model; do not store only a preformatted message and call it structured data. Keep renderer-only bounded diff data separate from the external JSON v1 metadata projection; JSON v1 omits raw revision bodies and diffs by default. Renderers must not perform core lookups.

Add `format: discord|json` to each webhook entry, defaulting absent format to `discord`. Refactor configuration routing to return validated destination specifications, preserving the existing HTTPS/URL checks and hook defaults rather than losing format metadata in a URL-only list. Define invalid formats as configuration errors: skip and log the affected entry without exposing its URL or silently switching serializers. Send Discord's existing content/allowed_mentions envelope for discord and the documented event envelope for json, both as application/json. A generic JSON receiver is a separate destination; sending the event envelope to a Discord endpoint does not make Discord display it. Share event filters and exception eligibility across serializers; a JSON destination must not bypass explicit LogException routing, private opt-in, deny-list, or rate limiting. Keep delivery failures isolated per destination and retain ErrorAlert's quiet behavior. Document the JSON schema and examples alongside implementation.

**dod:** First prove one real page edit fans out to a Discord destination and a JSON destination from the same event with the expected two captured requests. Then verify both formats for all registered hooks, unchanged legacy Discord payloads, default-format entries, mixed hook routing, invalid formats, malformed URLs, encoding failures, no eligible destinations, and a failed destination followed by a successful one. Assert UTC event times, schema/field types, event-specific fields, nullability, escaping, and absence of raw core objects, webhook secrets, hidden content, or extra exception details. Keep Discord's mention disabling, truncation, and diff fencing; do not apply its 2,000-character content limit to the whole JSON envelope. Verify both formats preserve filters and exception safeguards, run the full suite and appropriate static checks, and demonstrate that changing a Discord template leaves JSON fixtures unchanged. If the shared model cannot preserve payload parity or safe data projection, revise the contract before converting remaining handlers.

**merge gate:** Phases 0-2 verified; phase 1 coverage available as the Discord compatibility baseline; schema v1 field map and fixtures defined before broad handler conversion; no Discord access

---

### phase 4 - novawebhook rebrand and migration

**scope:** Make NovaWebhook the canonical extension identity after dual-format behavior is established. Inventory and consistently update extension.json identity, namespaces/autoloading, services, configuration names, logger channels, message keys, tests, Docker paths/project naming, README installation/loading examples, AGENTS.md, and package metadata or script defaults that use the old name. Preserve authorship, license, existing hosted repository URLs, historical notes, and this plan's location. Do not rename the remote repository or publish/move artifacts. Treat Discord as a supported renderer in user-facing documentation rather than the extension's identity.

Write an explicit old-to-new configuration map. Default migration policy: canonical NovaWebhook settings with legacy Discord-prefixed aliases for one transition release; detect explicitly supplied settings before defaults obscure them, use canonical values when both forms are explicitly set, never merge duplicate endpoint arrays, and log deprecation/conflict messages without secrets. Keep hook names and JSON schema v1 stable. Document the wfLoadExtension and directory-name migration, including what old loading names can actually support; do not promise a compatibility alias for a missing directory. Update registrations and consumers together in one complete rename commit. Keep previously synchronized REL1_44 unchanged.

**dod:** A fresh installation loads NovaWebhook, resolves all services and hook handlers, and passes the dual-destination page-edit slice and full suite. Migration fixtures cover legacy-only settings, canonical-only settings, conflicting explicit settings, absent settings/defaults, and exception opt-in combinations; each event reaches each intended destination once. Verify old configuration cannot disable an explicit canonical privacy setting or double-register handlers. Check the repository for old identifiers and document the intentional compatibility/historical/URL references. Validate package source paths and naming without running the publish script. If a promised alias cannot work with MediaWiki's loading/configuration lifecycle, revise and document the migration contract before declaring the rebrand complete.

**merge gate:** Phase 3 verified; explicit rename inventory and configuration precedence map; preserve JSON v1, hook routing keys, and notification behavior; no Discord access or publication

---

### phase 5 - release readiness and documentation

**scope:** Reconcile README requirements, branch example, supported formats/schema, migration guide, hook descriptions, and core RenameUser wording with the final NovaWebhook implementation. Update AGENTS.md's architecture and runtime baseline after the code changes. Reconcile extension metadata and development dependencies with the final implementation. Reassess the earlier provisional `3.2.0` version against the rebrand and compatibility contract; select and document the release version before changing metadata. Update relevant existing backlog entries only when their work is actually complete, preserving notes metadata. Keep lint-only housekeeping separate. Record readiness without publishing a package or tag.

**dod:** Run the Docker integration suite with unfiltered output, `composer check`, and `composer phan` in their documented environments; report exact totals, remaining skips, and any failures with attribution. Verify manifest registration, service names, requirements, installation/migration examples, format configuration, schema v1, and the 17-hook inventory agree. Inspect the complete diff and commit completed work. Recheck the dual-destination page-save slice, legacy settings migration, and exception opt-in behavior for both formats. If required checks cannot run or touched behavior still has skipped coverage, report incomplete readiness with the blocker instead of claiming the extension ready. Record branch/commit evidence separately from any later remote landing or deployment.

**merge gate:** Phases 0-4 verified; release version selected against the final migration contract; required tooling installed or a documented runnable container equivalent; no Discord access and no publication

---

## status

| phase | status |
| --- | --- |
| 0 - 1.46 compatibility baseline | closed in 314a490 |
| 1 - hook behavior and coverage | closed in 9981de3 |
| 2 - bounded modernization | closed in 9ceec2d |
| 3 - structured events and dual webhook formats | verified, uncommitted |
| 4 - novawebhook rebrand and migration | not started |
| 5 - release readiness and documentation | not started |
