# MediaWiki 1.44 to 1.46 compatibility findings

## evidence and branch preparation

Inspected on 2026-11-21. NovaDiscord is version `3.1.0`, requires MediaWiki `>= 1.44.0`, and builds its integration environment with PHP 8.4 and REL1_44.

Fetched NovaDiscord origin before branch preparation. Remote trunk and REL1_44 both pointed to `c714dc9`. Local trunk contained nine additional commits, ending at `f5da86625a8d2f8ac0fe52549979e3e19643d1cc`. Created local REL1_44 tracking its remote and fast-forwarded it to trunk; `git diff trunk REL1_44` was empty. Created REL1_46 from that synchronized commit. No remote refs were pushed. The plan commit belongs only to REL1_46; application code and version metadata are unchanged by planning.

Core reference clone: `/tmp/nova-mediawiki-rel146`, from the [Wikimedia mirror](https://github.com/wikimedia/mediawiki). It contains shallow REL1_44, REL1_45, and REL1_46 snapshots. The initial Gerrit clone at `/tmp/nova-mediawiki-reference` stalled and was stopped after the mirror clone succeeded; that incomplete directory is not the reference. Temporary paths are disposable; use the recorded SHAs and permanent source links to reproduce findings.

| core ref | inspected SHA | minimum PHP | core PHPUnit | core codesniffer / Phan config |
| --- | --- | --- | --- | --- |
| REL1_44 | `81202f0b064bb1a398529516e25843674055cab9` | 8.1.0 | 9.6.34 | 46.0.0 / 0.15.1 |
| REL1_45 | `a753f119b32321879fc44bf1b0d9865787fd02da` | 8.2.0 | 9.6.34 | 48.0.0 / 0.17.0 |
| REL1_46 | `d88c752b54c6052fb4cce261b140c88589420eed` | 8.3.0 | 9.6.34 | 51.0.1 / 0.20.0 |

Versions above come from each snapshot's composer.json, not historical initial-release dependency versions. Source: [1.44 Composer](https://github.com/wikimedia/mediawiki/blob/81202f0b064bb1a398529516e25843674055cab9/composer.json), [1.45 Composer](https://github.com/wikimedia/mediawiki/blob/a753f119b32321879fc44bf1b0d9865787fd02da/composer.json), [1.46 Composer](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/composer.json). Core branch snapshots include maintenance updates; this is not a comparison of 1.44.0 and 1.46.0 tags.

## hook inventory

Compared interface declarations in all three core refs and checked REL1_46's DeprecatedHooks registry. All 17 registered hooks remain available; their PHP signatures are unchanged across these snapshots. None is registered as a deprecated hook. Seven old interface class names remain deprecated aliases in 1.46. Changing a PHP import does not require changing the hook string used in extension.json or operator webhook configuration.

| hook | 1.46 interface namespace | action and behavior checks |
| --- | --- | --- |
| PageDeleteComplete | `MediaWiki\Page\Hook` | Keep; verify log timestamp versus content revision time and remove unnecessary WikiPage construction |
| PageMoveComplete | `MediaWiki\Hook` | Keep; retain seven untyped interface parameters and typed private helper |
| PageSaveComplete | `MediaWiki\Storage\Hook` | Keep; retain null/minor/bot/namespace filters and NS_FILE exclusion |
| PageUndeleteComplete | `MediaWiki\Page\Hook` | Keep; eight typed parameters remain unchanged; test partial restoration and revision count |
| ArticleRevisionVisibilitySet | `MediaWiki\RevisionDelete\Hook` | Update import from `MediaWiki\Hook`; real-operation test needed |
| ArticleProtectComplete | `MediaWiki\Page\Hook` | Keep; verify protect/unprotect payloads |
| AfterImportPage | `MediaWiki\Import\Hook` | Update import from `MediaWiki\Hook`; replace skipped test |
| ArticleMergeComplete | `MediaWiki\Hook` | Keep; replace skipped test and verify performer context |
| LocalUserCreated | `MediaWiki\Auth\Hook` | Keep; verify manual and autocreated accounts |
| BlockIpComplete | `MediaWiki\Specials\Hook` | Update import from `MediaWiki\Hook`; verify block/reblock and non-user targets |
| UnblockUserComplete | `MediaWiki\Specials\Hook` | Update import from `MediaWiki\Hook`; keep existing notification semantics |
| UserGroupsChanged | `MediaWiki\User\Hook` | Keep; seven parameters already match; retain performer=false silence |
| UploadComplete | `MediaWiki\Upload\Hook` | Update import from `MediaWiki\Hook`; replace skipped test, check reuploads |
| FileDeleteComplete | `MediaWiki\Hook` | Keep; replace skipped test for old version and whole-file deletion |
| FileUndeleteComplete | `MediaWiki\Specials\Hook` | Update import from `MediaWiki\Hook`; replace skipped test, compare API and web restoration |
| RenameUserComplete | `MediaWiki\RenameUser\Hook` | Keep; core provides this interface in all three refs; replace skipped test and correct extension-only documentation |
| LogException | `MediaWiki\Exception\Hook` | Update import from `MediaWiki\Hook`; preserve quiet logger, opt-in, explicit routing, deny-list, rate limit |

The imports to change are in ArticleAlert (two), UserAlert (two), FileAlert (two), and ErrorAlert (one). PageAlert and ExtensionAlert need no interface namespace migration. Many core directory names gained capitalization in 1.46; path changes alone are not PHP namespace changes.

Source: [1.46 hook runner](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/HookContainer/HookRunner.php), [deprecated hooks registry](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/HookContainer/DeprecatedHooks.php), and the individual interfaces under the namespaces above. The three-ref interface scan found identical method signatures, including UserGroupsChanged's old/new membership maps and PageUndeleteComplete's restored page IDs.

No hook additions or removals are required for compatibility. Do not restore TitleMoveComplete or add RollbackComplete: the existing PageMoveComplete and PageSaveComplete paths already cover those event families; RollbackComplete is deprecated in core.

## runtime and tooling differences

- PHP 8.4 satisfies core's 1.46 minimum; changing the PHP base image is unnecessary for the first compatibility milestone
- Set both Docker build arguments, `MEDIAWIKI_MAJOR_VERSION` and `MEDIAWIKI_BRANCH`; changing only the manifest does not exercise 1.46
- 1.46 generates local PHPUnit configuration from `phpunit.xml.template`; old core entrypoints/configurations were removed. Core's `phpunit:entrypoint` Composer alias still delegates to `phpunit`, which generates config. The current wrapper is therefore a verification target, not a proven breakage
- NovaDiscord declares PHPUnit `^12`, but integration tests run under the core installation's PHPUnit 9.6.34. The extension's empty bootstrap is not sufficient for host-only integration tests. Choose and document the actual dependency owner before updating versions
- HttpRequestFactory's constructor remains ServiceOptions, LoggerInterface, and optional telemetry; create() remains without a native return type in all three snapshots. No observed signature break forces replacement of the existing HTTP double
- Core still uses LoggerFactory's static facade in ServiceWiring. The extension's logger SPI wrapper remains valid; no evidence supports replacing it with a presumed LoggerFactory service
- Removed block write methods are relevant to test fixtures: use core BlockUser/UnblockUser services, not DatabaseBlock::insert/update/delete. Current block tests already use the factories
- User group mutation should use UserGroupAssignmentService, rather than removed SpecialUserRights helper methods. Current runtime handler is already compatible
- Modern HookHandlers/service registration is already present; 1.45's removal of old hook handler styles does not require registration redesign

Primary source: [1.46 release notes in the inspected source](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/RELEASE-NOTES-1.46), [1.45 release notes](https://github.com/wikimedia/mediawiki/blob/a753f119b32321879fc44bf1b0d9865787fd02da/RELEASE-NOTES-1.45), and [HttpRequestFactory](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/Http/HttpRequestFactory.php).

## modernization choices

| approach | benefit | cost and main tradeoff | decision |
| --- | --- | --- | --- |
| Existing hooks, canonical imports, targeted improvements | Small compatibility surface; preserves public routing keys and payloads | Some callbacks still lack actor/time data | Selected for this upgrade plan |
| Selective domain event adoption | Typed event data, explicit performer, stronger transaction semantics | Must prove timing, coverage, suppression, and single delivery for each replaced callback | Follow-up candidate after coverage exists |
| Replace event handlers with generic log publishing | Central actor, reason, and timestamp data | Reworks filtering/routing; overlaps saves, files, and existing log notifications; not every event is a log event | Reject for this upgrade |

Domain events existed in 1.44 and expanded through 1.45/1.46. They are not universally required in 1.46. [DomainEventIngress](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/DomainEvent/DomainEventIngress.php) registers through `DomainEventIngresses`; avoid the deprecated `DomainEventSubscribers` alias. [Core event documentation](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/docs/Events.md) describes the transaction and deferred processing goals.

The strongest candidate is [PageHistoryVisibilityChangedEvent](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/Page/Event/PageHistoryVisibilityChangedEvent.php). Core emits it with the performing user, visibility map, suppression flag, reason, and log timestamp. It could replace RequestContext lookup for that notification while retaining the old routing key. It excludes archived revisions; inspect parity before expanding any coverage. PageProtectionChanged, PageMoved, PageDeleted, and PageLatestRevisionChanged offer related opportunities, but the latter can represent import/restore updates as well as edits and can create duplicate notifications if adopted without a cause matrix.

FileUpload supplies an explicit `$reupload` boolean and could replace the history lookup if its emission paths match desired UploadComplete semantics. Keep one notification source. The smaller selected change is `getHistory(1)` because [LocalFile::getHistory](https://github.com/wikimedia/mediawiki/blob/d88c752b54c6052fb4cce261b140c88589420eed/includes/FileRepo/File/LocalFile.php) accepts a limit and queries oldimage; the current code loads every old file version just to test whether any exist.

Other bounded improvements:

- Use TitleFactory::newFromPageIdentity for deletion/restoration URL formatting rather than constructing WikiPage objects; WikiPageFactory remains needed for current move handling unless that is separately simplified
- Resolve an empty webhook route before formatting expensive diffs or loading file history; preserve explicit-only LogException routing
- Fetch parent revision data once for delta and diff generation instead of calling getPreviousRevision from both formatters
- Keep hook adapters broad where interfaces are untyped; PHP parameter narrowing is not a valid modernization
- Audit RequestContext-dependent import, merge, rename, and visibility actors under actual operations, including maintenance/API contexts; an injected context alone does not manufacture an actor absent from the hook
- Check temporary users and hidden revision/file data against existing public link/content behavior; do not request privileged content merely to populate Discord messages
- Inspect the unused sendAlert timestamp parameter before promising timestamp corrections. Delete/restore currently pass the old content revision time, and the JSON payload does not include the parameter at all

## verification performed and remaining gaps

- Fetched origin, inspected clean working tree, fast-forwarded REL1_44, created REL1_46, and verified equal baseline commit IDs
- Compared all 17 hook interface signatures across three core snapshots, confirmed the seven aliases and checked the deprecated hook registry
- A temporary PHP script required all 17 REL1_46 interfaces, DiscordAlert, and the six concrete handler classes; it printed `17 hook interfaces and 6 handlers loaded successfully against REL1_46` and exited 0. This validates declaration compatibility only, not autoload/service construction or hook execution
- Inspected HttpRequestFactory signatures, core Composer scripts, relevant hook callers, logger wiring, and domain event candidates
- Docker Compose is available (v5.4.0); daemon access succeeded with sandbox escalation (server 29.7.2). Host Composer is absent. Host PHP is available; runtime probe used PHP 8.5.2
- Integration/static suites were not run during this documentation-only task. No 1.46 runtime support is claimed yet
- Seven integration tests currently call markTestSkipped. The visibility test invokes HookContainer directly, so it does not verify the revision deletion call path
- Extension tests currently contain 22 test methods, including the seven skips; this is a source count, not an execution result

Next: open phase 0 with `plan-manager` for execution, or `plan-open` for bookkeeping. The runtime floor, preserved event model, and provisional 3.2.0 version are planning assumptions; no additional approval gate is imposed by this plan.
