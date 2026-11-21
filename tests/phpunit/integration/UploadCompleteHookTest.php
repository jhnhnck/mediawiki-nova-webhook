<?php
/**
 * NovaDiscord - UploadComplete Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\FileAlert::onUploadComplete
 */
class UploadCompleteHookTest extends NovaDiscordIntegrationTestCase {
    public function testUploadSendsWebhook(): void {
        $file = $this->uploadFile();
        $this->assertTrue($file->exists());
        $this->assertNotification('uploaded');
        $this->mockHttpFactory->reset();
        \MediaWiki\Utils\MWTimestamp::setFakeTime(time() + 2);
        $this->uploadFile();
        $this->assertNotification('uploaded');
        \MediaWiki\Utils\MWTimestamp::setFakeTime(false);
    }
}
