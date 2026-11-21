<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord;

final class DiscordRenderer {
    public function __construct(private readonly int $maxChars) {
    }

    public function render(WebhookEvent $event): array {
        $data = $event->data;
        $actorData = $event->actor;
        $pageData = $event->page;
        $actor = $actorData === null ? '' : $this->userLink($actorData);
        $page = $pageData === null ? '' : $this->pageLink($pageData);
        $reason = $this->formatMessage($data['reason'] ?? '');
        $message = match ($event->hook) {
            'PageSaveComplete' => $this->message(
                $data['created'] ? 'discord-create' : 'discord-edit',
                $actor, $page, $this->revisionText($data['revision']), $this->formatMessage($data['summary'])
            ),
            'PageDeleteComplete' => $this->message(
                'discord-articledelete', $actor, $page, $reason, $data['revision_count']
            ),
            'PageUndeleteComplete' => $this->message(
                'discord-articleundelete', $actor,
                $data['created'] ? '' : $this->message('discord-undeleterev'), $page, $reason
            ),
            'PageMoveComplete' => $this->message(
                'discord-titlemove', $actor, $this->pageLink($data['old_page']), $page,
                $reason, $this->revisionText($data['revision'])
            ),
            'ArticleRevisionVisibilitySet' => $this->message(
                'discord-revvisibility', $actor, $data['revision_count'], $page
            ),
            'ArticleProtectComplete' => $this->message(
                'discord-articleprotect', $actor, $page, $reason, implode(', ', $data['protections'])
            ),
            'AfterImportPage' => $this->message(
                'discord-afterimportpage', $actor, $page, $data['revision_count'], $data['imported_revision_count']
            ),
            'ArticleMergeComplete' => $this->message(
                'discord-articlemergecomplete', $actor, $this->pageLink($data['source_page']), $page
            ),
            'LocalUserCreated' => $this->message('discord-localusercreated', $actor),
            'BlockIpComplete' => $this->message(
                'discord-blockipcomplete', $actor, $this->userLink($data['target']),
                $reason, $this->expiryText($data['expiry'])
            ),
            'UnblockUserComplete' => $this->message(
                'discord-unblockusercomplete', $actor, $this->userLink($data['target'])
            ),
            'UserGroupsChanged' => $this->message(
                'discord-usergroupschanged', $actor, $this->userLink($data['target']), $reason,
                $data['added'] ? '+ ' . implode(', ', $data['added']) : '',
                $data['removed'] ? '- ' . implode(', ', $data['removed']) : ''
            ),
            'UploadComplete' => $this->message(
                'discord-uploadcomplete', $actor, $data['reupload'] ? $this->message('discord-uploadnewver') : '',
                $this->link($data['filename'], $pageData['url'] ?? ''), $this->formatMessage($data['comment']),
                $this->formatBytes($data['size']), $data['width'], $data['height'], $data['mime_type']
            ),
            'FileDeleteComplete' => $this->message(
                'discord-filedeletecomplete', $actor, $this->link($data['filename'], $pageData['url'] ?? ''), $reason
            ),
            'FileUndeleteComplete' => $this->message('discord-fileundeletecomplete', $actor, $page, $reason),
            'RenameUserComplete' => $this->message(
                'discord-renameusercomplete', $actor, '*' . $data['old_name'] . '*',
                $this->link($data['new_name'], $data['new_user_url'])
            ),
            'LogException' => '[' . $data['class'] . '] ' . $data['detail'],
        };
        $message = preg_replace('/\s+/', ' ', trim($message));
        if ($event->diff !== null) {
            $message .= "\n" . $event->diff;
        }
        return ['content' => $this->truncateForDiscord($message), 'allowed_mentions' => ['parse' => []]];
    }

    private function message(string $key, mixed ...$params): string {
        return wfMessage($key, ...$params)->inContentLanguage()->plain();
    }

    private function link(string $text, string $url): string {
        return '[' . $text . '](<' . $url . '>)';
    }

    private function pageLink(array $page): string {
        return $this->link($page['title'], $page['url']);
    }

    private function userLink(array $user): string {
        if ($user['url'] === null) {
            return $this->message('discord-userlinks', $user['name'], 'n/a', 'n/a');
        }
        return $this->message(
            'discord-userlinks', $this->link($this->truncate($user['name'], 25), $user['url']),
            $this->link($this->message('discord-talk'), $user['talk_url']),
            $this->link($this->message('discord-contribs'), $user['contributions_url'])
        );
    }

    private function revisionText(array $revision): string {
        return $this->message(
            'discord-revisionlinks', $this->link($this->message('discord-diff'), $revision['url']),
            $revision['minor'] ? $this->message('discord-minor') : '',
            $this->message(
                'discord-size', $revision['delta'] === null ? (string)$revision['size'] : sprintf('%+d', $revision['delta'])
            )
        );
    }

    private function expiryText(string $expiry): string {
        $time = strtotime($expiry);
        return $time ? date($this->message('discord-blocktimeformat'), $time) : $expiry;
    }

    private function truncate(string $text, int $length): string {
        return $length > 0 && mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text;
    }

    private function formatMessage(string $message): string {
        if ($message === '') {
            return '';
        }
        $message = preg_replace('/\s+/', ' ', trim($message));
        return '`' . $this->truncate(addcslashes($message, '`@'), $this->maxChars) . '`';
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $power = (int)min(floor($bytes ? log($bytes, 1024) : 0), count($units) - 1);
        return round($bytes / (1 << (10 * $power)), 2) . ' ' . $units[$power];
    }

    private function truncateForDiscord(string $message): string {
        if (mb_strlen($message) <= 2000) {
            return $message;
        }
        $separator = "\n```diff\n";
        $position = mb_strpos($message, $separator);
        if ($position === false || $position >= 2000 - mb_strlen($separator)) {
            return mb_substr($message, 0, 1997) . '...';
        }
        $tail = "\n// ... (truncated)\n```";
        return mb_substr($message, 0, 2000 - mb_strlen($tail)) . $tail;
    }
}
