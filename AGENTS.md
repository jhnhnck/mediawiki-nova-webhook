# NovaDiscord agent instructions

This is the canonical entry point for Codex in this repository. Project workflow
rules belong here; reuse installed global skills for reusable procedures.

## working rules

- Ask before interacting with Discord, including sending test webhooks. Use the
  in-memory HTTP test double for local verification.
- Commit completed task changes at logical boundaries without asking for approval
  of staging, grouping, or messages. Preserve unrelated edits. Follow explicit
  requests to leave work uncommitted. Publishing and deployment require their own
  authorization.
- Check the current time at the start of a conversation. During the late-night
  period after 12:30 AM America/New_York, suggest a natural stopping point before
  continuing.
- Include a valid reason with every lint suppression, including noqa comments.
- Preserve these rules during unrelated tasks; update them when the user requests
  a workflow change.

## project and architecture

NovaDiscord is an MIT-licensed PHP MediaWiki extension that sends Discord webhook
notifications for wiki events. The development container uses PHP 8.4 and MediaWiki
REL1_44; the manifest requires MediaWiki >= 1.44.0. Read the current version and
configuration defaults from [extension.json](extension.json).

| Location | Role |
| --- | --- |
| `includes/DiscordAlert.php` | Shared transport, filters, message formatting, diff and truncation helpers |
| `includes/NovaDiscordConfig.php` | Typed configuration access and webhook URL validation |
| `includes/{Page,User,Article,File,Extension,Error}Alert.php` | Event hook handlers |
| `includes/NovaDiscordLoggerSpi.php` | Logger SPI adapter |
| `ServiceWiring.php` | `NovaDiscord.Config` and `NovaDiscord.LoggerSpi` services |
| `extension.json` | Hook interfaces, handler services, config and autoload registration |
| `i18n/` | Localized messages |
| `tests/phpunit/integration/` | MediaWiki hook integration tests |

## code conventions and pitfalls

- Inject dependencies through constructors and register them in the handler's
  `services` array in `extension.json`. Do not call
  `MediaWikiServices::getInstance()` from hook methods.
- Access extension settings through `NovaDiscordConfig` and its `ServiceOptions`;
  do not read `$wg*` globals in handlers. Use `MW_INSTALL_PATH` instead of `global $IP`.
- Use the injected logger: debug for tracing, error for failures; do not use
  `wfDebugLog()`. `NovaDiscord.LoggerSpi` wraps the static logger facade because a
  `LoggerFactory` service is not guaranteed. Preserve `ErrorAlert`'s quiet logging
  behavior to avoid exception feedback loops.
- Route webhook URLs through `getWebhooksForHook()`; require HTTPS and
  `FILTER_VALIDATE_URL` validation.
- Encode payloads with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE |
  JSON_UNESCAPED_SLASHES`. Sanitize diff text with
  `mb_convert_encoding(..., 'UTF-8', 'UTF-8')`.
- Prefer the actual revision, block, or file timestamp. Use `time()` only when the
  hook supplies no event timestamp.
- Implement MediaWiki hook interfaces. Call `isEnabled()` before work in event
  handlers that use the shared filters.
- Use `EditResult::isNullEdit()` for `DiscordNoNull`; a size comparison is insufficient.
- Skip `NS_FILE` in PageSave handling because `FileAlert` handles file events.
- Register `PageMoveComplete`, not `TitleMoveComplete`.
- For hooks without a performing-user parameter, the existing ArticleAlert and
  ExtensionAlert handlers use `RequestContext::getMain()->getUser()`.
- Keep `LogException` explicit-only: it requires an explicit webhook hook entry
  and `DiscordPrivateExceptionAlerts = true`. Preserve exception rate limiting
  and deny-list filtering.
- Use spaces for indentation, lowercase inline comments without trailing periods,
  American English, and semicolons or regular dashes instead of em-dashes.

## verification

Use the installed `feature-completion`, `docker-commands`, and `commit-style`
skills when relevant. Project commands come from [composer.json](composer.json),
[.dev/docker-compose.yml](.dev/docker-compose.yml), and
[.phpcs.xml](.phpcs.xml).

| Command | Purpose |
| --- | --- |
| `composer test` | Build and run the Docker MediaWiki integration environment |
| `composer test-all` | Same integration suite with unfiltered output |
| `composer test-stop` | Same suite stopping on the first failure |
| `composer check` | PHP parallel lint, PHP_CodeSniffer, and minus-x checks |
| `composer phan` | Static analysis using the MediaWiki Phan configuration |

`composer test` requires Docker Compose; it does not run a standalone host PHPUnit
suite. Without host Composer, its equivalent is:

```sh
docker compose --file .dev/docker-compose.yml run --build --quiet-build --rm --remove-orphans job-tests
```

The container installs MediaWiki and uses MariaDB. Integration tests replace
`HttpRequestFactory` with [MockRequestFactory](tests/doubles/MockRequestFactory.php)
through [NovaDiscordIntegrationTestCase](tests/phpunit/integration/NovaDiscordIntegrationTestCase.php).
Inspect current tests for coverage; skipped stubs are not verified behavior.

Run checks appropriate to the touched code and report missing prerequisites or
failures. For instruction-only changes, inspect the diff, reference paths, and
workflow consistency; do not run the application suite just to validate Markdown.
Keep lint-only housekeeping separate from feature changes. Use the global commit
message conventions and existing Git history.

## notes and migration compatibility

- [notes/to-do.md](notes/to-do.md) remains the backlog; preserve its state and follow
  its priority, effort, completion, and metadata format when updating it.
- [notes/features/novadiscord.md](notes/features/novadiscord.md) describes the
  consuming wiki configuration. Its `devel/NovaDiscord/` path belongs to that
  deployment, not this repository's checkout.
- [notes/agents.md](notes/agents.md) is preserved as the legacy architecture and
  workflow reference. This file supersedes its agent rules, including the old
  commit approval requirement. Its version, test counts, and method summaries may
  be stale; check the source before relying on them.
- [notes/style/commit_style.md](notes/style/commit_style.md) remains a historical
  style reference; use the global `commit-style` skill and the authorization above
  instead of its old commit gate.
- [notes/.meta.md](notes/.meta.md) documents note organization and metadata. Its
  instruction to read `notes/agents.md` first is superseded by this root entry
  point. Keep note-specific metadata conventions when editing notes.
- No Claude skills, commands, rules, custom agents, hooks, MCP configuration, or
  plan state were present at migration. No global skills or runtime settings need
  to be installed for this migration. Preserve any future plan's existing path
  and distinguish verified work from committed, merged, or deployed work.
- [scripts/publish-package.zsh](scripts/publish-package.zsh) publishes signed
  artifacts using [package.conf](package.conf). Do not run it to validate a
  migration or instruction change.
