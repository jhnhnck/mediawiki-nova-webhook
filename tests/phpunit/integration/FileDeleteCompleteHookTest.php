<?php
/**
 * NovaWebhook - FileDeleteComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\FileAlert::onFileDeleteComplete
 */
class FileDeleteCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testFileDeleteSendsWebhook(): void {
        $file = $this->uploadFile();
        \MediaWiki\Utils\MWTimestamp::setFakeTime(time() + 2);
        $file = $this->uploadFile();
        $old = $file->getHistory(1)[0];
        $this->mockHttpFactory->reset();
        $this->doApiRequestWithToken([
            'action' => 'delete', 'title' => $file->getTitle()->getPrefixedText(),
            'oldimage' => $old->getArchiveName(), 'reason' => 'Old file deletion',
        ]);
        $this->assertNotification('Old file deletion');
        $this->mockHttpFactory->reset();
        $this->doApiRequestWithToken([
            'action' => 'delete', 'title' => $file->getTitle()->getPrefixedText(), 'reason' => 'Whole file deletion',
        ]);
        $this->runDeferredUpdates();
        $this->assertNotification('Whole file deletion');
        \MediaWiki\Utils\MWTimestamp::setFakeTime(false);
    }
}
