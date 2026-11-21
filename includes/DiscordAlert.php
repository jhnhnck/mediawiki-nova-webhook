<?php
/**
 * NovaDiscord - Hooks
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\Content\TextContent;
use MediaWiki\Revision\SlotRecord;
use Psr\Log\LoggerInterface;
use Wikimedia\Diff\ComplexityException;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\DiffOpAdd;
use Wikimedia\Diff\DiffOpChange;
use Wikimedia\Diff\DiffOpDelete;

abstract class DiscordAlert {
    private HttpRequestFactory $httpFactory;
    private RevisionLookup $revLookup;
    protected TitleFactory $titleFactory;
    private UrlUtils $urlUtils;
    protected LoggerInterface $logger;
    protected NovaDiscordConfig $novaConfig;

    public function __construct(HttpRequestFactory $httpFactory,
                                RevisionLookup $revLookup,
                                TitleFactory $titleFactory,
                                UrlUtils $urlUtils,
                                NovaDiscordConfig $novaConfig,
                                LoggerSpi $loggerSpi) {
        $this->httpFactory = $httpFactory;
        $this->revLookup = $revLookup;
        $this->titleFactory = $titleFactory;
        $this->urlUtils = $urlUtils;
        $this->novaConfig = $novaConfig;
        $this->logger = $loggerSpi->getLogger('nova-discord');
    }

    protected function sendEvent(WebhookEvent $event): void {
        $destinations = $this->novaConfig->getDestinationsForHook($event->hook);
        $payloads = [];
        foreach ($destinations as $destination) {
            $format = $destination['format'];
            try {
                if (!isset($payloads[$format])) {
                    $payload = $format === 'discord'
                        ? (new DiscordRenderer($this->novaConfig->getMaxChars()))->render($event)
                        : $event->toArray();
                    $payloads[$format] = json_encode(
                        $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
                $request = $this->httpFactory->create($destination['url'], [
                    'method' => 'POST', 'postData' => $payloads[$format],
                ], __METHOD__);
                $request->setHeader('Content-Type', 'application/json');
                $status = $request->execute();
                if (!$status->isOK()) {
                    $this->logger->error('Webhook delivery failed for {hook} ({format}, HTTP {status})', [
                        'hook' => $event->hook, 'format' => $format, 'status' => $request->getStatus(),
                    ]);
                }
            } catch (\Throwable $error) {
                // error details can contain destination credentials or event content
                $this->logger->error('Webhook delivery failed for {hook} ({format}, {exception})', [
                    'hook' => $event->hook, 'format' => $format, 'exception' => get_class($error),
                ]);
            }
        }
    }

    protected function describeUser(User $user): array {
        $contributions = SpecialPage::getTitleFor('Contributions', $user->getName())->getCanonicalURL();
        return [
            'id' => $user->getId(), 'name' => $user->getName(),
            'url' => $user->isAnon() ? $contributions : $user->getUserPage()->getCanonicalURL(),
            'talk_url' => $user->getTalkPage()->getCanonicalURL(), 'contributions_url' => $contributions,
        ];
    }

    protected function describePage(\MediaWiki\Title\Title $title, ?int $id = null): array {
        return [
            'id' => $id ?? $title->getArticleID(), 'namespace' => $title->getNamespace(),
            'title' => $title->getPrefixedText(), 'url' => $title->getCanonicalURL(),
        ];
    }

    protected function describeRevision(RevisionRecord $revision, ?RevisionRecord $parent): array {
        $title = $this->titleFactory->newFromPageIdentity($revision->getPage());
        return [
            'id' => $revision->getId(), 'parent_id' => $revision->getParentId(),
            'size' => $revision->getSize(), 'delta' => $parent ? $revision->getSize() - $parent->getSize() : null,
            'minor' => $revision->isMinor(),
            'url' => $title->getCanonicalURL(['diff' => 'prev', 'oldid' => $revision->getId()]),
        ];
    }

    // checks if alert should be sent based on bot/namespace/user filters
    protected function isEnabled(string $hookName, ?int $namespace, User $user): bool {
        if ($this->novaConfig->getDestinationsForHook($hookName) === []) {
            return false;
        }

        if ($this->novaConfig->isNoBots() && $user->isBot()) {
            return false;
        }

        if ($namespace !== null && in_array($namespace, $this->novaConfig->getDisabledNS())) {
            return false;
        }

        $userName = $user->getName();
        if ($userName && in_array($userName, $this->novaConfig->getDisabledUsers())) {
            return false;
        }

        return true;
    }

    private const DIFF_BLOCK_BUDGET = 1500;

    /* --- Util Functions --- */

    protected static function truncateString(string $text, int $length): string {
        if ($length > 0 && mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . '...';
        }

        return $text;
    }

    protected function getParentRevision(RevisionRecord $revision): ?RevisionRecord {
        return $revision->getParentId() ? $this->revLookup->getPreviousRevision($revision) : null;
    }

    /**
     * Computes a diff block string for the given revision vs its parent.
     * Returns a markdown ```diff code block, truncated to DIFF_BLOCK_BUDGET chars.
     * Returns null if content is not text-based.
     */
    protected function getDiffBlock(RevisionRecord $revision, ?RevisionRecord $parent): ?string {
        $newContent = $revision->getContent(SlotRecord::MAIN);
        if (!($newContent instanceof TextContent)) {
            return null;
        }
        $newText = mb_convert_encoding($newContent->getText(), 'UTF-8', 'UTF-8');

        $parentId = $revision->getParentId();
        if ($parentId) {
            $oldContent = $parent ? $parent->getContent(SlotRecord::MAIN) : null;
            $oldText = ($oldContent instanceof TextContent)
                ? mb_convert_encoding($oldContent->getText(), 'UTF-8', 'UTF-8')
                : '';
        } else {
            // Page creation — show new content as additions (first N lines)
            $oldText = '';
        }

        $oldLines = explode("\n", $oldText);
        $newLines = explode("\n", $newText);

        try {
            $diff = new Diff($oldLines, $newLines);
        } catch (ComplexityException) {
            return null;
        }

        $lines = [];
        $budget = self::DIFF_BLOCK_BUDGET;
        $truncated = false;

        foreach ($diff->edits as $op) {
            if ($op instanceof DiffOpDelete || $op instanceof DiffOpChange) {
                foreach ($op->orig as $line) {
                    $entry = '- ' . $line;
                    if (mb_strlen($entry) + 1 > $budget) {
                        $truncated = true;
                        break 2;
                    }
                    $lines[] = $entry;
                    $budget -= mb_strlen($entry) + 1;
                }
            }
            if ($op instanceof DiffOpAdd || $op instanceof DiffOpChange) {
                foreach ($op->closing as $line) {
                    $entry = '+ ' . $line;
                    if (mb_strlen($entry) + 1 > $budget) {
                        $truncated = true;
                        break 2;
                    }
                    $lines[] = $entry;
                    $budget -= mb_strlen($entry) + 1;
                }
            }
        }

        if ($lines === []) {
            return null;
        }

        if ($truncated) {
            $lines[] = '// ... (diff truncated)';
        }

        return "```diff\n" . implode("\n", $lines) . "\n```";
    }
}
