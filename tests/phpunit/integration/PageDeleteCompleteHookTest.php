<?php
/**
 * NovaWebhook - PageDeleteComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers MediaWiki\Extension\NovaWebhook\PageAlert::onPageDeleteComplete
 */
class PageDeleteCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testPageDeleteSendsWebhook(): void {
        // setup
        $services = $this->getServiceContainer();
        $title = $services->getTitleFactory()->newFromText('NovaTestPageDelete', NS_MAIN);
        $page = $services->getWikiPageFactory()->newFromTitle($title);

        // create page
        $status = $this->editPage($page, 'TBD', 'Created page');
        $this->assertTrue($status->isOK(), 'Page should be created successfully');
        $this->mockHttpFactory->reset();

        // delete page
        $user = $this->getTestUser();
        $this->deletePage($page, 'Deleted page', $user->getAuthority());
        $this->assertFalse($page->exists(), 'Page should be deleted successfully');

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
            . '>)) deleted [NovaTestPageDelete](<https://novawebhook.local/wiki/NovaT'
            . 'estPageDelete>) `Deleted page` (1 revisions deleted)","allowed_mention'
            . 's":{"parse":[]}}';

        // verify webhook payload
        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on page delete');
        $this->assertEquals(
            $expectedPayload, $payload['options']['postData'],
            'Webhook payload for page delete should match expected output'
        );
    }
}
