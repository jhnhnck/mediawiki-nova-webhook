<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord;

use InvalidArgumentException;

final class WebhookEvent {
    private const TYPES = [
        'PageDeleteComplete' => 'page.deleted',
        'PageMoveComplete' => 'page.moved',
        'PageSaveComplete' => 'page.updated',
        'PageUndeleteComplete' => 'page.restored',
        'ArticleRevisionVisibilitySet' => 'revision.visibility_changed',
        'ArticleProtectComplete' => 'page.protection_changed',
        'AfterImportPage' => 'page.imported',
        'ArticleMergeComplete' => 'page.histories_merged',
        'LocalUserCreated' => 'user.created',
        'BlockIpComplete' => 'user.blocked',
        'UnblockUserComplete' => 'user.unblocked',
        'UserGroupsChanged' => 'user.groups_changed',
        'UploadComplete' => 'file.uploaded',
        'FileDeleteComplete' => 'file.version_deleted',
        'FileUndeleteComplete' => 'file.versions_restored',
        'RenameUserComplete' => 'user.renamed',
        'LogException' => 'exception.logged',
    ];

    public function __construct(
        public readonly string $hook,
        public readonly int $timestamp,
        public readonly ?array $actor,
        public readonly ?array $page,
        public readonly array $data,
        public readonly ?string $diff = null
    ) {
        if (!isset(self::TYPES[$hook])) {
            throw new InvalidArgumentException('Unknown webhook event hook');
        }
        $this->validateValues([$actor, $page, $data]);
    }

    private function validateValues(array $values): void {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->validateValues($value);
            } elseif ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Webhook event data must contain only JSON-compatible values');
            }
        }
    }

    public function toArray(): array {
        $type = self::TYPES[$this->hook];
        if ($this->hook === 'PageSaveComplete' && $this->data['created']) {
            $type = 'page.created';
        }
        return [
            'schema_version' => 1,
            'event' => $type,
            'hook' => $this->hook,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $this->timestamp),
            'actor' => $this->actor,
            'page' => $this->page,
            'data' => $this->data,
        ];
    }
}
