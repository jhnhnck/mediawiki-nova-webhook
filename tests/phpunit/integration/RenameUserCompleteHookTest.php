<?php
/**
 * NovaDiscord - RenameUserComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\ExtensionAlert::onRenameUserComplete
 */
class RenameUserCompleteHookTest extends NovaDiscordIntegrationTestCase {
    public function testRenameUserSendsWebhook(): void {
        $user = $this->getMutableTestUser()->getUser();
        $performer = $this->getTestSysop()->getUser();
        \MediaWiki\Context\RequestContext::getMain()->setUser($performer);
        $rename = new \MediaWiki\RenameUser\RenameuserSQL(
            $user->getName(), 'Nova Renamed User', $user->getId(), $performer
        );
        $this->assertStatusGood($rename->renameUser());
        $this->runDeferredUpdates();
        $this->assertNotification('Nova Renamed User');
    }
}
