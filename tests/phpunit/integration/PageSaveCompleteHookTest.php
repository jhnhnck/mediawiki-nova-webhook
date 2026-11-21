<?php
/**
 * NovaWebhook - PageSaveComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\SlotRecord;

/**
 * @group Database
 * @covers MediaWiki\Extension\NovaWebhook\PageAlert::onPageSaveComplete
 */
class PageSaveCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testPageCreationSendsWebhook(): void {
        // create page
        $user = $this->getTestUser();
        $status = $this->editPage(
            'NovaTestPageCreate',
            'Not to be confused with [[NovaTestPageEdit]].',
            'Created page',
            0,
            $user->getAuthority()
        );
        $this->assertTrue($status->isOK(), 'Page should be created successfully');

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
            . '>)) created [NovaTestPageCreate](<https://novawebhook.local/wiki/NovaT'
            . 'estPageCreate>) ([diff](<https://novawebhook.local/index.php?title=Nov'
            . 'aTestPageCreate&diff=prev&oldid=1>)) (45) `Created page`\n```diff\n- '
            . '\n+ Not to be confused with [[NovaTestPageEdit]].\n```","allowed_menti'
            . 'ons":{"parse":[]}}';

        // verify webhook payload
        $this->assertCount(1, $this->getDiscordRequests());
        $payload = $this->getDiscordRequests()[0];
        $this->assertSame('POST', $payload['method']);
        $this->assertSame('application/json', $payload['headers']['Content-Type']);
        $this->assertSame(getenv('NOVA_WEBHOOK_URL'), $payload['url']);
        $this->assertNotNull($payload, 'Webhook payload should be captured on page creation');
        $this->assertEquals(
            $expectedPayload, $payload['options']['postData'],
            'Webhook payload for page creation should match expected output'
        );
    }

    public function testMinorEditOmitsDiffBlock(): void {
        // setup
        $status = $this->editPage('NovaTestPageMinor', 'Initial content.', 'Created page');
        $this->assertTrue($status->isOK(), 'Page should be created successfully');
        $this->mockHttpFactory->reset();

        // perform minor edit using WikiPage directly to pass EDIT_MINOR flag
        $user = $this->getTestUser();
        $services = $this->getServiceContainer();
        $title = $services->getTitleFactory()->newFromText('NovaTestPageMinor');
        $page = $services->getWikiPageFactory()->newFromTitle($title);
        $updater = $page->newPageUpdater($user->getAuthority());
        $updater->setContent(SlotRecord::MAIN, new WikitextContent('Initial content. (minor fix)'));
        $updater->saveRevision(
            \MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment('Fixed typo'),
            EDIT_MINOR
        );
        $this->assertTrue($updater->getStatus()->isOK(), 'Page should be edited successfully');

        // build expected payload — no diff block
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
            . '>)) edited [NovaTestPageMinor](<https://novawebhook.local/wiki/NovaTes'
            . 'tPageMinor>) ([diff](<https://novawebhook.local/index.php?title=NovaTe'
            . 'stPageMinor&diff=prev&oldid=2>)) (m) (+12) `Fixed typo`","allowed_ment'
            . 'ions":{"parse":[]}}';

        // verify webhook payload
        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on minor edit');
        $this->assertEquals(
            $expectedPayload, $payload['options']['postData'],
            'Minor edit webhook payload should not include diff block'
        );
    }

    public function testPageEditSendsWebhook(): void {
        // setup
        $status = $this->editPage('NovaTestPageEdit', 'TBD', 'Created page');
        $this->assertTrue($status->isOK(), 'Page should be created successfully');
        $this->mockHttpFactory->reset();

        // perform actual edit
        $user = $this->getTestUser();
        $status = $this->editPage(
            'NovaTestPageEdit',
            'The quick brown fox jumped over the lazy churg.',
            'Added content',
            0,
            $user->getAuthority()
        );
        $this->assertTrue($status->isOK(), 'Page should be edited successfully');

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
            . '>)) edited [NovaTestPageEdit](<https://novawebhook.local/wiki/NovaTest'
            . 'PageEdit>) ([diff](<https://novawebhook.local/index.php?title=NovaTest'
            . 'PageEdit&diff=prev&oldid=2>)) (+44) `Added content`\n```diff\n- TBD\n+'
            . ' The quick brown fox jumped over the lazy churg.\n```","allowed_mentio'
            . 'ns":{"parse":[]}}';

        // verify webhook payload
        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on page edit');
        $this->assertEquals(
            $expectedPayload, $payload['options']['postData'],
            'Webhook payload for page edit should match expected output'
        );
    }
}
