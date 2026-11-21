# webhook event formats

Each endpoint selects `format: discord` or `format: json`. An omitted format selects Discord. An invalid format skips that endpoint and logs a configuration warning. Endpoints require a valid HTTPS URL; hook selection and filters apply to both formats. Delivery remains synchronous, with no automatic retry. Failure at one destination does not prevent an attempt at the next destination.

```php
$wgNovaWebhookEndpoints = [
    ['url' => 'https://discord.com/api/webhooks/...', 'format' => 'discord'],
    ['url' => 'https://receiver.example.org/events', 'format' => 'json'],
];
```

Discord endpoints receive the existing `content` and `allowed_mentions` envelope. Discord messages are rendered from the shared event model, use the wiki content language, disable mentions, and preserve bounded diff fences when truncating to 2,000 characters. JSON endpoints receive metadata independently of message templates or Discord's content limit. Send raw event JSON to your own receiver; Discord's endpoint expects its Discord payload.

## json envelope version 1

```json
{
  "schema_version": 1,
  "event": "page.updated",
  "hook": "PageSaveComplete",
  "timestamp": "2026-11-21T20:16:10Z",
  "actor": {
    "id": 42,
    "name": "Example",
    "url": "https://wiki.example.org/wiki/User:Example",
    "talk_url": "https://wiki.example.org/wiki/User_talk:Example",
    "contributions_url": "https://wiki.example.org/wiki/Special:Contributions/Example"
  },
  "page": {
    "id": 123,
    "namespace": 0,
    "title": "Example page",
    "url": "https://wiki.example.org/wiki/Example_page"
  },
  "data": {
    "created": false,
    "summary": "Updated introduction",
    "revision": {
      "id": 456,
      "parent_id": 455,
      "size": 120,
      "delta": 12,
      "minor": false,
      "url": "https://wiki.example.org/index.php?title=Example_page&diff=prev&oldid=456"
    }
  }
}
```

The envelope has the same eight fields for every event. `schema_version` is integer 1; `event`, `hook`, and `timestamp` are strings. Time is UTC RFC 3339 with second precision. `actor` is null for exceptions; `page` is null for user and exception events. IDs are integers; a non-user block target uses a null ID and null links, and anonymous user ID is 0. Deleted-page IDs preserve the identity supplied by the hook. A revision's delta is null when no parent is available. `data` contains the fields below; consumers should tolerate additional fields within schema version 1. Breaking field/type/semantic changes require a new version.

## event data

| hook | event | data fields |
| --- | --- | --- |
| PageSaveComplete | `page.created` or `page.updated` | `created` boolean, `summary` string, `revision` object |
| PageDeleteComplete | `page.deleted` | `reason` string, `revision_count` integer |
| PageMoveComplete | `page.moved` | `old_page` page object, `reason` string, `revision` object; envelope page is destination |
| PageUndeleteComplete | `page.restored` | `reason` string, `revision_count` integer, `created` boolean |
| ArticleRevisionVisibilitySet | `revision.visibility_changed` | `revision_count` integer, `revision_ids` integer array |
| ArticleProtectComplete | `page.protection_changed` | `reason` string, `protections` map from action to protection level |
| AfterImportPage | `page.imported` | `revision_count` integer, `imported_revision_count` integer, `foreign_title` string |
| ArticleMergeComplete | `page.histories_merged` | `source_page` page object; envelope page is destination |
| LocalUserCreated | `user.created` | `autocreated` boolean; actor is the new user |
| BlockIpComplete | `user.blocked` | `target` user/target object, `reason` string, `expiry` MediaWiki timestamp or infinity string |
| UnblockUserComplete | `user.unblocked` | `target` user/target object |
| UserGroupsChanged | `user.groups_changed` | `target` user object, `reason` string, `added` and `removed` string arrays |
| UploadComplete | `file.uploaded` | `filename` string, `reupload` boolean, `comment` string, `size`, `width`, `height` integers, `mime_type` string |
| FileDeleteComplete | `file.version_deleted` | `filename` string, `archive_name` string or null, `reason` string |
| FileUndeleteComplete | `file.versions_restored` | `versions` selected file version identifiers or null for all versions, `reason` string |
| RenameUserComplete | `user.renamed` | `user_id` integer, `old_name`, `new_name`, `new_user_url` strings |
| LogException | `exception.logged` | `class` string, `detail` bounded string matching existing exception detail policy |

Raw revision bodies, diffs, framework objects, credentials, and stack traces are excluded from JSON v1. Summaries and reasons remain raw strings rather than Discord markup; receivers must escape them for their output context. A hidden revision comment is omitted from the summary. Bounded diff data exists only inside the event model for Discord rendering and is never projected into JSON.

## timestamps and eligibility

Saves and moves use revision time, uploads use file time, blocks use block time, registrations use registration time, and page deletion/restoration use the supplied log entry time. Other hooks supply no operation timestamp and use the callback time, including unblock and file-version deletion; these must not reuse the older block/file creation time.

Exception notifications require the private exception opt-in and an explicitly selected LogException hook for the destination. The deny-list, suppressed/expected-error exclusions, quiet error handler, and five-minute APCu fingerprint limit apply to both formats. Automatic group changes remain silent. File page saves remain silent; whole-file deletion is reported by the page deletion handler. Restoring a page and its file versions emits distinct page and file events.
