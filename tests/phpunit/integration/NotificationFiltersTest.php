<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Revision\SlotRecord;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\DiscordAlert::isEnabled
 * @covers \MediaWiki\Extension\NovaDiscord\PageAlert::onPageSaveComplete
 */
class NotificationFiltersTest extends NovaDiscordIntegrationTestCase {
    /** @dataProvider provideFilters */
    public function testFilteredEditsStaySilent(array $config, array $groups): void {
        $this->overrideConfigValues($config);
        $user = $this->getMutableTestUser($groups);
        $this->mockHttpFactory->reset();
        $this->assertStatusGood($this->editPage('NovaFiltered', 'Content', '', 0, $user->getAuthority()));
        $this->assertCount(0, $this->getDiscordRequests());
    }

    public static function provideFilters(): array {
        return [
            'bots' => [['DiscordNoBots' => true], ['bot']],
            'namespace' => [['DiscordDisabledNS' => [NS_MAIN]], []],
            'no endpoints' => [['DiscordWebhooks' => []], []],
            'hook exclusion' => [['DiscordDefaultHooks' => ['PageDeleteComplete']], []],
        ];
    }

    public function testNullEditStaysSilent(): void {
        $this->editPage('NovaNull', 'Same content');
        $this->mockHttpFactory->reset();
        $this->assertStatusOK($this->editPage('NovaNull', 'Same content', 'Null summary'));
        $this->assertCount(0, $this->getDiscordRequests());
    }

    public function testMinorEditFilter(): void {
        $this->overrideConfigValue('DiscordNoMinor', true);
        $page = $this->getExistingTestPage('NovaMinorFilter');
        $this->mockHttpFactory->reset();
        $updater = $page->newPageUpdater($this->getTestUser()->getAuthority());
        $updater->setContent(SlotRecord::MAIN, new WikitextContent('Minor change'));
        $updater->saveRevision(CommentStoreComment::newUnsavedComment('Minor'), EDIT_MINOR);
        $this->assertStatusGood($updater->getStatus());
        $this->assertCount(0, $this->getDiscordRequests());
    }
}
