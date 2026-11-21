<?php
/**
 * NovaDiscord - Article and revision hooks
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord;

use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\ArticleMergeCompleteHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Import\Hook\AfterImportPageHook;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Page\Hook\ArticleProtectCompleteHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\RevisionDelete\Hook\ArticleRevisionVisibilitySetHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;

class ArticleAlert extends DiscordAlert implements ArticleRevisionVisibilitySetHook, ArticleProtectCompleteHook, AfterImportPageHook, ArticleMergeCompleteHook {
    private UserFactory $userFactory;

    public function __construct(HttpRequestFactory $httpFactory,
                                RevisionLookup $revLookup,
                                TitleFactory $titleFactory,
                                UrlUtils $urlUtils,
                                UserFactory $userFactory,
                                NovaDiscordConfig $novaConfig,
                                LoggerSpi $loggerSpi) {
        $this->userFactory = $userFactory;
        parent::__construct($httpFactory, $revLookup, $titleFactory, $urlUtils, $novaConfig, $loggerSpi);
    }

    /**
     * Called after committing revision visibility changes to the database
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleRevisionVisibilitySet
     */
    public function onArticleRevisionVisibilitySet($title, $ids, $visibilityChangeMap) {
        $hookName = 'ArticleRevisionVisibilitySet';

        $user = $this->userFactory->newFromUserIdentity(RequestContext::getMain()->getUser());

        if (!$this->isEnabled($hookName, $title->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($title), [
                'revision_count' => count($visibilityChangeMap), 'revision_ids' => array_values($ids),
            ]
        ));
    }

    /**
     * Called when a page is protected (or unprotected)
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleProtectComplete
     */
    public function onArticleProtectComplete($wikiPage, $user, $protect, $reason) {
        $hookName = 'ArticleProtectComplete';

        if (!$this->isEnabled($hookName, $wikiPage->getTitle()->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($wikiPage->getTitle()), [
                'reason' => $reason, 'protections' => $protect,
            ]
        ));
    }

    /**
     * Called when a page is imported
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/AfterImportPage
     */
    public function onAfterImportPage($title, $foreignTitle, $revCount, $sRevCount, $pageInfo) {
        $hookName = 'AfterImportPage';

        $user = $this->userFactory->newFromUserIdentity(RequestContext::getMain()->getUser());

        if (!$this->isEnabled($hookName, $title->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($title), [
                'revision_count' => $revCount, 'imported_revision_count' => $sRevCount,
                'foreign_title' => $foreignTitle->getFullText(),
            ]
        ));
    }

    /**
     * Called when article histories are merged
     * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleMergeComplete
     */
    public function onArticleMergeComplete($targetTitle, $destTitle) {
        $hookName = 'ArticleMergeComplete';

        $user = $this->userFactory->newFromUserIdentity(RequestContext::getMain()->getUser());

        if (!$this->isEnabled($hookName, $destTitle->getNamespace(), $user)) {
            return;
        }

        $this->sendEvent(new WebhookEvent(
            $hookName, time(), $this->describeUser($user), $this->describePage($destTitle), [
                'source_page' => $this->describePage($targetTitle),
            ]
        ));
    }
}
