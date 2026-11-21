<?php
/**
 * NovaWebhook - BlockIpComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\UserAlert::onBlockIpComplete
 */
class BlockIpCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testBlockIpSendsWebhook(): void {
        $targetUser = $this->getTestUser()->getUser();
        $blockingUser = $this->getTestSysop()->getUser();

        $blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
        $status = $blockUserFactory->newBlockUser(
            $targetUser,
            $blockingUser,
            'infinity',
            'Test block reason'
        )->placeBlock();

        $this->assertTrue($status->isOK(), 'User should be blocked successfully');

        $blockingUsername = $blockingUser->getName();
        $blockingUserScored = str_replace(' ', '_', $blockingUsername);
        $targetUsername = $targetUser->getName();
        $targetUserScored = str_replace(' ', '_', $targetUsername);

        $expectedPayload = '{"content":"[' . $blockingUsername . '](<https://novawebhook.local/wiki/User:' . $blockingUserScored . '>) ([t](<https://novawebhook.local/wiki/User_talk:' . $blockingUserScored . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/' . $blockingUserScored . '>)) blocked [' . $targetUsername . '](<https://novawebhook.local/wiki/User:' . $targetUserScored . '>) ([t](<https://novawebhook.local/wiki/User_talk:' . $targetUserScored . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/' . $targetUserScored . '>)) `Test block reason` (infinity)","allowed_mentions":{"parse":[]}}';

        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on user block');
        $this->assertEquals($expectedPayload, $payload['options']['postData'], 'Webhook payload for user block should match expected output');
    }
}
