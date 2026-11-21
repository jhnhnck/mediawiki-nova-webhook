# NovaWebhook to-do list

_see the [meta](#meta) section at the end of this file for format reference._

---

## tasks

### code

- ⭕ `low priority` `low effort` include restored revision count in the Discord undelete message; JSON already includes it (`includes/DiscordRenderer.php`)
- ⭕ `low priority` `low effort` finalize invalid chars list in `DiscordRenderer::formatMessage` (`includes/DiscordRenderer.php`)

### docs

- ⭕ `low priority` `low effort` add new example image to README (`README.md`)

### meta

- `high priority` `low effort` assign any to-dos without an effort or category; update priorities; move completed and sort all

---

## completed

### code

- 🔴 `21 November 2026` deletion/restoration build Title directly from PageIdentity without constructing a WikiPage; Title remains necessary for canonical URLs
- 🔴 `21 November 2026` parent revision is retrieved once for size metadata and bounded edit diff generation

### tests

- 🔴 `21 November 2026` UserGroupsChanged integration coverage uses UserGroupAssignmentService and verifies automatic changes remain silent
- 🔴 `21 November 2026` selected revision restoration verifies the JSON revision count
- 🔴 `21 November 2026` all six former stub-only hook tests execute real MediaWiki operations with both serializers

---

## meta

### format

open item: `- ⭕ \`priority\` \`effort\` description`

completed item: `- 🔴 \`26 March 2026\` description`

priority levels (highest to lowest): `high priority`, `medium priority`, `low priority`, `future idea`

effort levels: `no effort`, `low effort`, `medium effort`, `high effort`, `very high effort`

items without a checkbox are recurring; they repeat each maintenance cycle rather than being tracked as one-time work. these live in the `## meta` section.

when an item is completed, move it to the `# completed` section under the appropriate category, strip the priority/effort tags, and add a date stamp. sort completed entries chronologically within each category (oldest first). remove completed entries that are no longer relevant and not referenced by any open to-do. increment `total_completed` in the metadata each time an item is marked done.

when adding a new item, sort it into the appropriate section by topic, or add a new section if none fits. assign priority and effort tags. if the scope, priority, or effort is unclear, ask clarifying questions before adding. split larger projects into multiple entries.

### sections

- **to-do** - active items grouped by area; sorted within each section by priority (high first)
- **completed** - done items kept for reference; sorted chronologically; pruned when no longer relevant
- **meta** - this section; describes the doc format and holds recurring maintenance tasks

### metadata

```yaml
last_updated: 21 November 2026
total_completed: 5
```
