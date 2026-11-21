<?php
/**
 * NovaWebhook - ArticleUndelete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers MediaWiki\Extension\NovaWebhook\PageAlert::onPageUndeleteComplete
 */
class PageUndeleteCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testRestoringSelectedRevisionReportsCount(): void {
        $user = $this->getTestSysop();
        \MediaWiki\Utils\MWTimestamp::setFakeTime('20200101000000');
        $first = $this->editPage('NovaPartialRestore', 'First', '', 0, $user->getAuthority());
        \MediaWiki\Utils\MWTimestamp::setFakeTime('20200102000000');
        $this->editPage('NovaPartialRestore', 'Second', '', 0, $user->getAuthority());
        $page = $this->getExistingTestPage('NovaPartialRestore');
        $this->deletePage($page, 'Delete both', $user->getAuthority());
        $this->mockHttpFactory->reset();
        $restore = $this->getServiceContainer()->getUndeletePageFactory()->newUndeletePage(
            $page, $user->getAuthority()
        );
        $restore->setUndeleteOnlyTimestamps([$first->getNewRevision()->getTimestamp()]);
        $this->assertStatusGood($restore->undeleteIfAllowed('Restore one'));
        $this->runDeferredUpdates();
        $this->assertNotification('Restore one');
        $requests = $this->mockHttpFactory->getCaptured();
        $json = json_decode($requests[1]['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $json['data']['revision_count']);
        $this->assertTrue($json['data']['created']);
        \MediaWiki\Utils\MWTimestamp::setFakeTime(false);
    }

    public function testPageUndeletionSendsWebhook(): void {
        // setup
        $services = $this->getServiceContainer();
        $title = $services->getTitleFactory()->newFromText('NovaTestPageUndelete', NS_MAIN);
        $page = $services->getWikiPageFactory()->newFromTitle($title);
        $undeletePageFactory = $services->getUndeletePageFactory();

        // create page
        $status = $this->editPage($page, 'TBD', 'Created page');
        $this->assertTrue($status->isOK(), 'Page should be created successfully');

        // delete page
        $user = $this->getTestSysop();
        $this->deletePage($page, 'Deleted page', $user->getAuthority());
        $this->assertFalse($page->exists(), 'Page should be deleted successfully');
        $this->mockHttpFactory->reset();

        // undelete page
        $pageUndeleter = $undeletePageFactory->newUndeletePage($page, $user->getAuthority());
        $status = $pageUndeleter->undeleteIfAllowed('Undeleted page for test case');
        $this->assertTrue($status->isOK(), 'Page should be undeleted successfully');

        // build expected payload
        $username = $user->getUser()->getName();
        $userScored = str_replace(' ', '_', $username);
        $expectedPayload = '{"content":"['
            . $username
            . '](<https://novawebhook.local/wiki/User:'
            . $userScored
            . '>) ([t](<https://novawebhook.local/wiki/User_talk:'
            . $userScored
            . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/'
            . $userScored
            . '>)) undeleted [NovaTestPageUndelete](<https://novawebhook.local/wiki/N'
            . 'ovaTestPageUndelete>) `Undeleted page for test case`","allowed_mention'
            . 's":{"parse":[]}}';

        // verify webhook payload
        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on page undelete');
        $this->assertEquals(
            $expectedPayload, $payload['options']['postData'],
            'Webhook payload for page undelete should match expected output'
        );
    }
}
