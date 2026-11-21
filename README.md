# NovaWebhook

NovaWebhook sends MediaWiki events to Discord and structured JSON webhook receivers. Handlers collect event data once; the Discord renderer turns that data into localized messages. Each endpoint selects its format and hook subscriptions.

Existing NovaDiscord installations should follow the [migration guide](docs/migration-novawebhook.md). The Git repository URL remains unchanged.

## requirements

- MediaWiki 1.46 or later, with PHP 8.3 or later as required by core
- An HTTPS receiver URL; Discord messages require a [Discord webhook URL](https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks)
- APCu when enabling private exception notifications, for fingerprint rate limiting

Use `REL1_46` for NovaWebhook 4.0.0. `REL1_44` retains the NovaDiscord baseline for older installations. Development and integration verification use PHP 8.4 with MediaWiki REL1_46.

## installation

Clone the release branch into the MediaWiki extensions directory:

```sh
git clone --depth=1 --branch REL1_46 https://github.com/jhnhnck/mediawiki-extensions-NovaDiscord extensions/NovaWebhook
```

Configure LocalSettings.php:

```php
wfLoadExtension('NovaWebhook');
$wgNovaWebhookEndpoints = [
    ['url' => 'https://discord.com/api/webhooks/...'],
    ['url' => 'https://receiver.example.org/events', 'format' => 'json'],
];
```

## configuration

| setting | type | effective default | behavior |
| --- | --- | --- | --- |
| `$wgNovaWebhookEndpoints` | array | `[]` | Destination entries; an empty list disables delivery |
| `$wgNovaWebhookDefaultHooks` | array or null | `null` | Default subscriptions; null selects all ordinary hooks, excluding LogException |
| `$wgNovaWebhookNoBots` | bool | `true` | Suppress bot actions |
| `$wgNovaWebhookNoMinor` | bool | `false` | Suppress minor page edits |
| `$wgNovaWebhookNoNull` | bool | `true` | Suppress null edits using EditResult |
| `$wgNovaWebhookDisabledNS` | array of integers | `[]` | Namespace IDs to exclude |
| `$wgNovaWebhookDisabledUsers` | array of strings | `["Redirect fixer"]` | Performing users to exclude |
| `$wgNovaWebhookMaxChars` | int | `500` | Bound Discord summaries/reasons and private exception details |
| `$wgNovaWebhookPrivateExceptionAlerts` | bool | `false` | Opt into private exception notifications |
| `$wgNovaWebhookExceptionDenyList` | array | `[]` | Suppress exception classes or class/message substring pairs |

Each endpoint accepts:

| field | required | behavior |
| --- | --- | --- |
| `url` | yes | Valid HTTPS URL |
| `format` | no | `discord` or `json`; omitted selects Discord, invalid values skip the endpoint |
| `hooks` | no | Array of hook names, or null for all ordinary hooks; omitted uses NovaWebhookDefaultHooks |

```php
$wgNovaWebhookDefaultHooks = ['PageSaveComplete', 'PageDeleteComplete'];
$wgNovaWebhookEndpoints = [
    ['url' => 'https://discord.com/api/webhooks/...'],
    ['url' => 'https://receiver.example.org/edits', 'format' => 'json', 'hooks' => ['PageSaveComplete']],
];
```

See [event formats and JSON schema v1](docs/webhook-events.md) for the envelope, field types, timestamp sources, and delivery behavior. JSON contains raw metadata strings, without revision bodies or diffs. Discord messages disable mentions, include bounded edit diffs, and retain their 2,000-character limit. Delivery is synchronous; a failed endpoint does not prevent attempts at other endpoints, and retries are not automatic.

Legacy Discord-prefixed settings remain accepted for the transition release. Explicit NovaWebhook settings take precedence, including false, null, and empty arrays; endpoint lists are never merged. See the [complete migration map](docs/migration-novawebhook.md#installation-and-configuration).

## notifications

| hooks | events |
| --- | --- |
| PageSaveComplete | Page creation and edits; file namespace saves excluded |
| PageDeleteComplete, PageMoveComplete, PageUndeleteComplete | Page deletion, movement, and restoration |
| ArticleRevisionVisibilitySet, ArticleProtectComplete | Revision visibility and page protection changes |
| AfterImportPage, ArticleMergeComplete | Imports and history merges |
| LocalUserCreated, RenameUserComplete | Account creation and core user renames |
| BlockIpComplete, UnblockUserComplete, UserGroupsChanged | Blocks, unblocks, and manual group changes |
| UploadComplete, FileDeleteComplete, FileUndeleteComplete | Uploads and file-version deletion/restoration |
| LogException | Private exception notifications, explicitly selected and opted into |

Whole-file deletion uses the page-deletion notification. Restoring a page and its file versions produces separate page and file events. A history merge may also produce a redirect edit. Automatic group changes remain silent.

Exception endpoints require both `$wgNovaWebhookPrivateExceptionAlerts = true` and explicit LogException selection. The deny-list accepts class names or arrays with `class` and optional case-sensitive `messageContains`. Suppressed and expected navigation errors are excluded; duplicate fingerprints are limited to one event per five minutes through APCu. Exception delivery errors remain quiet to avoid feedback loops.

## development

The integration suite installs MediaWiki and MariaDB in Docker Compose and replaces HttpRequestFactory with an in-memory double. It exercises all 17 registered hooks and both serializers without contacting Discord. It also covers configuration migration, filters, destination failures, and exception safeguards. Skipped tests do not count as verified behavior.

| command | purpose |
| --- | --- |
| `composer test` | Build and run integration tests with filtered output |
| `composer test-all` | Run the suite with unfiltered output |
| `composer test-stop` | Stop on the first failure |
| `composer check` | PHP lint, codesniffer, and executable-file checks |
| `composer phan` | Static analysis; supported parser fallback works without php-ast |

Without host Composer:

```sh
docker compose --file .dev/docker-compose.yml run --build --quiet-build --rm --remove-orphans job-tests zsh ./entry.zsh --all
```

Core owns the integration PHPUnit installation and generates its local configuration. The extension's development dependencies provide lint and static-analysis tools; its empty standalone bootstrap is not a MediaWiki integration environment. Install those dependencies with Composer before linting, and set `MW_INSTALL_PATH` to a matching core checkout when running Phan outside the test container. Phan targets PHP 8.3; runtime tests use PHP 8.4.

Defaults and descriptions live under `attributes.NovaWebhook.ConfigDefaults` in extension.json. `NovaWebhook.Config` resolves explicit canonical and legacy settings before applying those defaults into ServiceOptions. Unset extension settings are not exported as main-config globals during the transition. See [AGENTS.md](AGENTS.md) for project workflow and verification requirements.

## license

NovaWebhook is [MIT licensed](LICENSE) and is based on jayktaylor's [mw-discord](https://github.com/jayktaylor/mw-discord).
