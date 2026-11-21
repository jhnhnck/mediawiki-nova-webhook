# Bugs - NovaWebhook for MediaWiki 1.46

## open

None.

## closed

B001 - important, fixed: composer check reproduced 53 pre-existing codesniffer errors before the upgrade. Separate phase 5 housekeeping resolves inherited documentation, imports, whitespace, and long fixtures without lint suppressions. PHP lint, codesniffer, and minus-x now pass (/tmp/nova-final-check.log); the 71-test canonical suite still passes (/tmp/nova-lint-tests.log). See log.md for the commit record.
