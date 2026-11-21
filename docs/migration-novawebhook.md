# migrating from NovaDiscord

NovaWebhook requires MediaWiki 1.46 or later and offers Discord messages and structured JSON endpoints. REL1_44 retains the old NovaDiscord baseline. This branch changes the installed extension identity; the Git repository URL remains unchanged.

## installation and configuration

Place the checkout in `extensions/NovaWebhook` and load it once:

```php
wfLoadExtension('NovaWebhook');
$wgNovaWebhookEndpoints = [
    ['url' => 'https://discord.com/api/webhooks/...'],
    ['url' => 'https://receiver.example.org/events', 'format' => 'json'],
];
```

For an existing checkout, rename the installed `extensions/NovaDiscord` directory to `extensions/NovaWebhook` and replace the old wfLoadExtension call. If you keep a custom directory, pass its manifest path explicitly as the second argument to wfLoadExtension. Loading `NovaDiscord` without a matching directory will not find this renamed installation. Do not load both names; PHP namespace aliases or duplicate extension registrations are not provided.

Discord-prefixed configuration remains accepted for one transition release. Canonical NovaWebhook settings win whenever explicitly supplied, including `false`, `null`, and empty arrays. Endpoint lists are never combined. Legacy and conflicting settings generate warnings containing setting names, without their values or URLs. Migrate these names together and remove old settings after checking the new configuration.

| legacy setting | canonical setting | effective default |
| --- | --- | --- |
| `$wgDiscordWebhooks` | `$wgNovaWebhookEndpoints` | `[]` |
| `$wgDiscordDefaultHooks` | `$wgNovaWebhookDefaultHooks` | `null` |
| `$wgDiscordNoBots` | `$wgNovaWebhookNoBots` | `true` |
| `$wgDiscordNoMinor` | `$wgNovaWebhookNoMinor` | `false` |
| `$wgDiscordNoNull` | `$wgNovaWebhookNoNull` | `true` |
| `$wgDiscordMaxChars` | `$wgNovaWebhookMaxChars` | `500` |
| `$wgDiscordDisabledNS` | `$wgNovaWebhookDisabledNS` | `[]` |
| `$wgDiscordDisabledUsers` | `$wgNovaWebhookDisabledUsers` | `["Redirect fixer"]` |
| `$wgDiscordPrivateExceptionAlerts` | `$wgNovaWebhookPrivateExceptionAlerts` | `false` |
| `$wgDiscordExceptionDenyList` | `$wgNovaWebhookExceptionDenyList` | `[]` |

Hook routing names and JSON schema version 1 are unchanged. Entries without `format` still use Discord. An explicit canonical `NovaWebhookPrivateExceptionAlerts = false` overrides legacy `true`. JSON destinations require the same exception opt-in and explicit LogException routing.

## extension internals

The PHP namespace is `MediaWiki\Extension\NovaWebhook`; services are `NovaWebhook.Config` and `NovaWebhook.LoggerSpi`. Message keys use `novawebhook-`, and the logger channel is `nova-webhook`. The package name is NovaWebhook. External consumers of the old PHP classes or service names must update; compatibility is provided for configuration settings only.

Effective defaults and descriptions remain in extension.json, under `attributes.NovaWebhook.ConfigDefaults`. They are resolved into ServiceOptions after checking explicit settings. Registering ordinary global defaults first would hide whether canonical `false`, `null`, or `[]` had been supplied, making reliable alias precedence impossible. Consumers should obtain settings through `NovaWebhook.Config`; unset extension settings are not exported as main-config globals during this transition.

No package publication, remote repository rename, webhook delivery, or consuming-wiki upgrade is part of this migration work. Test and deploy the renamed extension through your normal release process.
