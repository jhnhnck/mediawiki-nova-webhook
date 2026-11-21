<?php
/**
 * NovaWebhook - ArticleRevisionVisibilitySet Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Context\RequestContext;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\ArticleAlert::onArticleRevisionVisibilitySet
 */
class ArticleRevisionVisibilitySetHookTest extends NovaWebhookIntegrationTestCase {
    public function testArticleRevisionVisibilityChangeSendsWebhook(): void {
        $performer = $this->getTestSysop();
        $page = $this->getExistingTestPage('NovaTestRevVisibility');

        $this->editPage($page, 'Content 1', 'First revision', 0, $performer->getAuthority());
        $status2 = $this->editPage($page, 'Content 2', 'Second revision', 0, $performer->getAuthority());
        $revId = $status2->getNewRevision()->getId();
        $this->editPage($page, 'Content 3', 'Current revision', 0, $performer->getAuthority());
        $this->runDeferredUpdates();

        $this->mockHttpFactory->reset();

        // Set performer as context user (handler reads from RequestContext)
        \RequestContext::getMain()->setUser($performer->getUser());

        $list = \MediaWiki\RevisionDelete\RevisionDeleter::createList(
            'revision', RequestContext::getMain(), $page, [$revId]
        );
        $this->assertStatusGood($list->setVisibility([
            'value' => [\MediaWiki\Revision\RevisionRecord::DELETED_TEXT => 1], 'comment' => 'Hide text',
        ]));
        $this->runDeferredUpdates();

        $performerUsername = $performer->getUser()->getName();
        $performerUserScored = str_replace(' ', '_', $performerUsername);
        $pageTitle = 'NovaTestRevVisibility';

        $expectedPayload = '{"content":"[' . $performerUsername . '](<https://novawebhook.local/wiki/User:' . $performerUserScored . '>) ([t](<https://novawebhook.local/wiki/User_talk:' . $performerUserScored . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/' . $performerUserScored . '>)) changed visibility of 1 revisions on [' . $pageTitle . '](<https://novawebhook.local/wiki/' . $pageTitle . '>)","allowed_mentions":{"parse":[]}}';

        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on revision visibility change');
        $this->assertEquals($expectedPayload, $payload['options']['postData'], 'Webhook payload for revision visibility change should match expected output');
    }
}
