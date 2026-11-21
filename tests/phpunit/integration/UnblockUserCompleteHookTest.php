<?php
/**
 * NovaWebhook - UnblockUserComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\UserAlert::onUnblockUserComplete
 */
class UnblockUserCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testUnblockUserSendsWebhook(): void {
        $targetUser = $this->getTestUser()->getUser();
        $blockingUser = $this->getTestSysop()->getUser();
        $unblockingUser = $this->getTestSysop()->getUser();

        $blockUserFactory = $this->getServiceContainer()->getBlockUserFactory();
        $status = $blockUserFactory->newBlockUser(
            $targetUser,
            $blockingUser,
            'infinity',
            'Test block reason'
        )->placeBlock();
        $this->assertTrue($status->isOK(), 'User should be blocked successfully');

        $this->mockHttpFactory->reset();

        $unblockUserFactory = $this->getServiceContainer()->getUnblockUserFactory();
        $unblockStatus = $unblockUserFactory->newUnblockUser(
            $targetUser,
            $unblockingUser,
            'Test unblock reason'
        )->unblock();
        $this->assertTrue($unblockStatus->isOK(), 'User should be unblocked successfully');

        $unblockingUsername = $unblockingUser->getName();
        $unblockingUserScored = str_replace(' ', '_', $unblockingUsername);
        $targetUsername = $targetUser->getName();
        $targetUserScored = str_replace(' ', '_', $targetUsername);

        $expectedPayload = '{"content":"[' . $unblockingUsername . '](<https://novawebhook.local/wiki/User:' . $unblockingUserScored . '>) ([t](<https://novawebhook.local/wiki/User_talk:' . $unblockingUserScored . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/' . $unblockingUserScored . '>)) unblocked [' . $targetUsername . '](<https://novawebhook.local/wiki/User:' . $targetUserScored . '>) ([t](<https://novawebhook.local/wiki/User_talk:' . $targetUserScored . '>)|[c](<https://novawebhook.local/wiki/Special:Contributions/' . $targetUserScored . '>))","allowed_mentions":{"parse":[]}}';

        $payload = $this->getDiscordRequests()[0];
        $this->assertNotNull($payload, 'Webhook payload should be captured on user unblock');
        $this->assertEquals($expectedPayload, $payload['options']['postData'], 'Webhook payload for user unblock should match expected output');
    }
}
