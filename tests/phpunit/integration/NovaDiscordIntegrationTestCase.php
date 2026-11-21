<?php
/**
 * NovaDiscord - Abstract Integration Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\NovaDiscord\Tests\Doubles\MockRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Upload\UploadFromFile;
use MediaWiki\FileRepo\File\LocalFile;

/**
 * @group Database
 */
abstract class NovaDiscordIntegrationTestCase extends ApiTestCase {
    protected $mockHttpFactory;

    protected function setUp(): void {
        parent::setUp();
        $services = $this->getServiceContainer();

        $this->mockHttpFactory = new MockRequestFactory(
            new ServiceOptions(
                MockRequestFactory::CONSTRUCTOR_OPTIONS,
                $services->getMainConfig()
            ),
            LoggerFactory::getInstance('http'),
        );
        $this->setService('HttpRequestFactory', $this->mockHttpFactory);
    }

    protected function uploadFile(?string $name = null): LocalFile {
        $name ??= (new \ReflectionClass($this))->getShortName() . '.png';
        $user = $this->getTestSysop()->getUser();
        $temp = $this->getNewTempFile();
        copy(MW_INSTALL_PATH . '/tests/phpunit/data/media/1bit-png.png', $temp);
        $existing = $this->getServiceContainer()->getRepoGroup()->getLocalRepo()->newFile($name);
        if ($existing->exists()) {
            file_put_contents($temp, '\nnew version', FILE_APPEND);
        }
        $request = new FauxRequest([], true);
        $request->setUpload('file', [
            'name' => $name, 'type' => 'image/png', 'tmp_name' => $temp,
            'size' => filesize($temp), 'error' => '',
        ]);
        $upload = new UploadFromFile($user);
        $upload->initialize($name, $request->getUpload('file'));
        $this->assertStatusGood($upload->performUpload('Upload reason', 'File description', false, $user));
        $this->runDeferredUpdates();
        return $upload->getLocalFile();
    }

    protected function assertNotification(string $fragment, int $count = 1): void {
        $captured = $this->mockHttpFactory->getCaptured();
        $this->assertCount($count, $captured);
        $contents = [];
        foreach ($captured as $request) {
            $payload = json_decode($request['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
            $contents[] = $payload['content'];
            $this->assertSame(['parse' => []], $payload['allowed_mentions']);
        }
        $this->assertStringContainsString($fragment, implode('\n', $contents));
    }
}
