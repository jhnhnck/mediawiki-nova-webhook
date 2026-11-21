<?php
/**
 * NovaDiscord - UserGroupsChanged Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\UserAlert::onUserGroupsChanged
 */
class UserGroupsChangedHookTest extends NovaDiscordIntegrationTestCase {
    public function testUserGroupsChangedSendsWebhook(): void {
        $target = $this->getMutableTestUser()->getUser();
        $performer = $this->getTestSysop()->getUser();
        $this->overrideConfigValue('AddGroups', ['sysop' => ['bot']]);
        $this->getServiceContainer()->getUserGroupAssignmentService()->saveChangesToUserGroups(
            $performer, $target, ['bot'], [], [], 'Rights reason'
        );
        $this->assertNotification('changed rights');
        $this->mockHttpFactory->reset();
        $this->getServiceContainer()->getUserGroupManager()->addUserToGroup($target, 'autoconfirmed');
        $this->runDeferredUpdates();
        $this->assertCount(0, $this->mockHttpFactory->getCaptured());
    }
}
