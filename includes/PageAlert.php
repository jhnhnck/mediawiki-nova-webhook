<?php
/**
 * NovaWebhook - Page lifecycle hooks
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook;

use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\Hook\PageUndeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\UrlUtils;
use MediaWiki\Linker\LinkTarget;

class PageAlert extends WebhookAlert implements PageSaveCompleteHook, PageDeleteCompleteHook, PageMoveCompleteHook, PageUndeleteCompleteHook {
    private UserFactory $userFactory;

    public function __construct(HttpRequestFactory $httpFactory,
                                RevisionLookup $revLookup,
                                TitleFactory $titleFactory,
                                UrlUtils $urlUtils,
                                UserFactory $userFactory,
                                NovaWebhookConfig $novaConfig,
                                LoggerSpi $loggerSpi) {
        $this->userFactory = $userFactory;

        parent::__construct($httpFactory, $revLookup, $titleFactory, $urlUtils, $novaConfig, $loggerSpi);
    }

    /**
     * Stub function because hook definition doesn't allow types yet
     * @inheritDoc
     */
    public function onPageSaveComplete($wikiPage, $userIdentity, $summary, $flags, $revision, $editResult) {
        $this->handlePageSave($wikiPage, $userIdentity, (string)$summary, $revision, $editResult);
    }

    /**
     * Called when a page is created or edited
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
     */
    private function handlePageSave(WikiPage $wikiPage,
                                    UserIdentity $userIdentity,
                                    string $summary,
                                    RevisionRecord $revision,
                                    EditResult $editResult): void {
        $hookName = 'PageSaveComplete';
        $this->logger->debug('Completing hook ' . $hookName . ' on ' . $wikiPage);

        $user = $this->userFactory->newFromUserIdentity($userIdentity);

        // check if hook is enabled
        if (!$this->isEnabled($hookName, $wikiPage->getTitle()->getNamespace(), $user)) {
            return;
        }

        // filter out minor/null edits if configured
        if (($this->novaConfig->isNoMinor() && $revision->isMinor()) || ($this->novaConfig->isNoNull() && $editResult->isNullEdit())) {
            return;
        }

        // new files are handled by onUploadComplete instead
        if ($wikiPage->getTitle()->inNamespace(NS_FILE)) {
            return;
        }

        $parent = $this->getParentRevision($revision);
        $diff = null;
        foreach ($this->novaConfig->getDestinationsForHook($hookName) as $destination) {
            if ($destination['format'] === 'discord' && !$revision->isMinor()) {
                $diff = $this->getDiffBlock($revision, $parent);
                break;
            }
        }
        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $revision->getTimestamp()),
            $this->describeUser($user), $this->describePage($wikiPage->getTitle()), [
                'created' => $editResult->isNew(),
                'summary' => $revision->isDeleted(RevisionRecord::DELETED_COMMENT) ? '' : $summary,
                'revision' => $this->describeRevision($revision, $parent),
            ], $diff
        ));
    }

    /**
     * Called when a page is deleted
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageDeleteComplete
     */
    public function onPageDeleteComplete(ProperPageIdentity $page,
                                         Authority $deleter,
                                         string $reason,
                                         int $pageID,
                                         RevisionRecord $deletedRev,
                                         ManualLogEntry $logEntry,
                                         int $archivedRevisionCount): void {
        $hookName = 'PageDeleteComplete';
        $this->logger->debug('Completing hook ' . $hookName . ' on ' . $page);

        $user = $this->userFactory->newFromUserIdentity($deleter->getUser());
        $title = $this->titleFactory->newFromPageIdentity($page);

        if (!$this->isEnabled($hookName, $title->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $logEntry->getTimestamp()),
            $this->describeUser($user), $this->describePage($title, $pageID), [
                'reason' => $reason, 'revision_count' => $archivedRevisionCount,
            ]
        ));
    }

    /**
     * Stub function because hook definition doesn't allow types yet
     * @inheritDoc
     */
    public function onPageMoveComplete($old, $new, $user, $pageid, $redirid, $reason, $revision) {
        $this->handlePageMove($old, $new, $user, $pageid, $reason, $revision);
    }

    /**
     * Called when a page is moved
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageMoveComplete
     */
    private function handlePageMove(LinkTarget $old,
                                    LinkTarget $new,
                                    UserIdentity $userIdentity,
                                    int $pageid,
                                    string $reason,
                                    RevisionRecord $revision): void {
        $hookName = 'PageMoveComplete';
        $this->logger->debug('Completing hook ' . $hookName . ' on ' . $old);

        $user = $this->userFactory->newFromUserIdentity($userIdentity);

        // check if hook is enabled
        if (!$this->isEnabled($hookName, $old->getNamespace(), $user)) {
            return;
        }

        $oldTitle = $this->titleFactory->newFromLinkTarget($old);
        $newTitle = $this->titleFactory->newFromLinkTarget($new);
        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $revision->getTimestamp()),
            $this->describeUser($user), $this->describePage($newTitle, $pageid), [
                'old_page' => $this->describePage($oldTitle, $pageid), 'reason' => $reason,
                'revision' => $this->describeRevision($revision, $this->getParentRevision($revision)),
            ]
        ));
    }

    /**
     * Occurs after the undelete page request has been processed
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageUndeleteComplete
     */
	public function onPageUndeleteComplete(ProperPageIdentity $page,
                                           Authority $restorer,
                                           string $reason,
                                           RevisionRecord $restoredRev,
                                           ManualLogEntry $logEntry,
                                           int $restoredRevisionCount,
                                           bool $created,
                                           array $restoredPageIds): void {
        $hookName = 'PageUndeleteComplete';
        $this->logger->debug('Completing hook ' . $hookName . ' on ' . $page);

        $user = $this->userFactory->newFromUserIdentity($restorer->getUser());
        $title = $this->titleFactory->newFromPageIdentity($page);

        // check if hook is enabled
        if (!$this->isEnabled($hookName, $title->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, (int)wfTimestamp(TS_UNIX, $logEntry->getTimestamp()),
            $this->describeUser($user), $this->describePage($title, $page->getId()), [
                'reason' => $reason, 'revision_count' => $restoredRevisionCount, 'created' => $created,
            ]
        ));
    }
}
