<?php
/**
 * NovaWebhook - FileUndeleteComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaWebhook\FileAlert::onFileUndeleteComplete
 */
class FileUndeleteCompleteHookTest extends NovaWebhookIntegrationTestCase {
    public function testFileUndeleteSendsWebhook(): void {
        $file = $this->uploadFile();
        $this->doApiRequestWithToken(['action' => 'delete', 'title' => $file->getTitle()->getPrefixedText()]);
        $this->mockHttpFactory->reset();
        $this->doApiRequestWithToken([
            'action' => 'undelete', 'title' => $file->getTitle()->getPrefixedText(), 'reason' => 'File restoration',
        ]);
        $this->runDeferredUpdates();
        $this->assertNotification('File restoration', 2);
    }
}
