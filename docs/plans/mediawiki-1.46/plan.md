# NovaDiscord for MediaWiki 1.46

## goals

- Run NovaDiscord's registered notifications on MediaWiki REL1_46 with verified payloads and no unintended duplicate delivery
- Replace deprecated hook interface imports, establish real coverage for the seven skipped hook tests, and preserve routing and exception safeguards
- Reduce unnecessary page construction, revision lookups, and file history queries where source evidence supports the change
- Finish with consistent requirements, development commands, documentation, and recorded verification evidence on REL1_46

## non-goals

- Deploying the extension, publishing packages, sending Discord webhooks, or upgrading a consuming wiki's database
- Maintaining 1.44 compatibility on this branch; REL1_44 retains the synchronized baseline
- A wholesale domain event conversion, asynchronous delivery, retries, new notification categories, or a generic log event feed
- Updating every development dependency or fixing unrelated lint findings

## constraints

- Plan directory: `docs/plans/mediawiki-1.46/`; phase details remain in this file
- Implementation belongs on REL1_46; REL1_44 and trunk were equal at `f5da86625a8d2f8ac0fe52549979e3e19643d1cc` before this planning commit
- Branch synchronization is local. No remote refs were pushed; remote trunk and REL1_44 were both `c714dc9` when fetched
- Follow AGENTS.md, constructor injection, NovaDiscordConfig routing, HTTPS validation, payload encoding, bot/minor/null/namespace filters, file-save handling, and exception privacy/rate limiting
- Use the in-memory HTTP double. Interacting with Discord requires separate user authorization
- Core source snapshots and the full hook inventory are recorded in [compatibility.md](compatibility.md); refresh that evidence if core revisions change
- PHP 8.4 remains the default container runtime; target core requires PHP >=8.3.0. Do not infer minimum-runtime coverage from a PHP 8.4 run
- Integration tests use core's Composer/PHPUnit environment. The extension's standalone PHPUnit declaration and empty bootstrap do not establish an independent test environment
- Commit complete phase units under standing authorization. Merge, push, package publication, and deployment are separate from local verification

## accepted risks

Core release branches and unpinned build dependencies move; record exact versions at execution. The successful interface load probe does not establish runtime behavior. Seven skipped tests and one manually dispatched visibility hook leave coverage gaps; phase 1 retires those before modernization. Synchronous delivery remains an accepted existing limitation. Domain event conversion stays deferred because equivalent coverage, timing, and routing have not been demonstrated. The [pre-mortem](pre-mortem.md) records probes and pivot criteria; these assumptions have not been confirmed by the user.

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

**scope:** Apply small improvements behind phase 1's behavioral coverage. Build a Title directly from PageIdentity in deletion/restoration handlers instead of constructing a WikiPage solely for its Title; retain WikiPageFactory only where still needed. Limit upload history to one old version when testing whether history exists. Reuse parent-revision data across size and diff formatting where straightforward. Resolve routing before expensive diff/history work while preserving exception explicit-only behavior. Remove obsolete comments and aliases only in touched code. Review deletion/restoration timestamps against ManualLogEntry: the current code uses content revision time, and the transport currently ignores the timestamp argument. Either carry timestamps through a defined observable contract or remove the unused plumbing; do not claim that changing an unused argument changes Discord output. Prefer preserving the payload format in this phase.

Evaluate one bounded domain event candidate on paper against the completed tests: PageHistoryVisibilityChanged provides actor and log timestamp data without RequestContext. Keep the legacy hook as the selected implementation for this plan; a later event migration must preserve the `ArticleRevisionVisibilitySet` configuration key, active-revision coverage, suppression policy, and exactly one delivery. FileUpload is likewise an alternative to UploadComplete, not a second simultaneous notification source.

**dod:** New assertions demonstrate preserved payloads, counts, routing, and filters; a query limit or lookup count demonstrates each claimed performance improvement. The page-save slice and full suite still pass. Source review confirms no new service locator calls, narrowed untyped hook parameters, exception feedback loops, or hidden reads of globals. Record the timestamp contract decision and domain event comparison. Stop or defer any optimization that changes coverage or requires a larger architecture redesign; do not broaden this phase to make the optimization fit.

**merge gate:** Phase 1 verified with real-operation coverage for affected handlers; preserve public hook routing keys and existing payload format; no Discord access

---

### phase 3 - release readiness and documentation

**scope:** Update README requirements, branch example, hook descriptions, and core RenameUser wording; update AGENTS.md's runtime baseline after the container change. Reconcile extension metadata and development dependencies with the final implementation. Use provisional extension version `3.2.0` for the new MediaWiki floor and maintenance improvements, revising it if project release policy or final scope requires otherwise. Update relevant existing backlog entries only when their work is actually complete, preserving notes metadata. Keep lint-only housekeeping separate. Record readiness without publishing a package or tag.

**dod:** Run the Docker integration suite with unfiltered output, `composer check`, and `composer phan` in their documented environments; report exact totals, remaining skips, and any failures with attribution. Verify manifest registration, service names, requirements, installation examples, and the 17-hook inventory agree. Inspect the complete diff and commit completed work. Recheck the page-save slice and exception opt-in behavior. If required checks cannot run or touched behavior still has skipped coverage, report incomplete readiness with the blocker instead of claiming the extension ready. Record branch/commit evidence separately from any later remote landing or deployment.

**merge gate:** Phases 0-2 verified; required tooling installed or a documented runnable container equivalent; no Discord access and no publication

---

## status

| phase | status |
| --- | --- |
| 0 - 1.46 compatibility baseline | not started |
| 1 - hook behavior and coverage | not started |
| 2 - bounded modernization | not started |
| 3 - release readiness and documentation | not started |
