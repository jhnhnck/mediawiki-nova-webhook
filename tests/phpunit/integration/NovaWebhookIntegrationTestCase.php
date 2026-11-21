<?php
/**
 * NovaWebhook - Abstract Integration Test Case
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaWebhook\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\NovaWebhook\Tests\Doubles\MockRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Upload\UploadFromFile;
use MediaWiki\FileRepo\File\LocalFile;

/**
 * @group Database
 */
abstract class NovaWebhookIntegrationTestCase extends ApiTestCase {
    protected $mockHttpFactory;

    protected function setUp(): void {
        parent::setUp();
        $this->overrideConfigValue('NovaWebhookEndpoints', [
            ['url' => getenv('NOVA_WEBHOOK_URL')],
            ['url' => 'https://events.example.test/hook', 'format' => 'json'],
        ]);
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
        $captured = $this->getDiscordRequests();
        $this->assertCount($count, $captured);
        $contents = [];
        foreach ($captured as $request) {
            $payload = json_decode($request['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
            $contents[] = $payload['content'];
            $this->assertSame(['parse' => []], $payload['allowed_mentions']);
        }
        $this->assertStringContainsString($fragment, implode('\n', $contents));
    }

    protected function getDiscordRequests(): array {
        $requests = $this->mockHttpFactory->getCaptured();
        $discord = [];
        foreach ($requests as $request) {
            $payload = json_decode($request['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists('content', $payload)) {
                $discord[] = $request;
                continue;
            }
            $this->assertSame(1, $payload['schema_version']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $payload['timestamp']);
            $this->assertIsString($payload['event']);
            $this->assertIsString($payload['hook']);
            $this->assertIsArray($payload['data']);
            $fields = [
                'PageSaveComplete' => ['created', 'summary', 'revision'],
                'PageDeleteComplete' => ['reason', 'revision_count'],
                'PageMoveComplete' => ['old_page', 'reason', 'revision'],
                'PageUndeleteComplete' => ['reason', 'revision_count', 'created'],
                'ArticleRevisionVisibilitySet' => ['revision_count', 'revision_ids'],
                'ArticleProtectComplete' => ['reason', 'protections'],
                'AfterImportPage' => ['revision_count', 'imported_revision_count', 'foreign_title'],
                'ArticleMergeComplete' => ['source_page'],
                'LocalUserCreated' => ['autocreated'],
                'BlockIpComplete' => ['target', 'reason', 'expiry'],
                'UnblockUserComplete' => ['target'],
                'UserGroupsChanged' => ['target', 'reason', 'added', 'removed'],
                'UploadComplete' => ['filename', 'reupload', 'comment', 'size', 'width', 'height', 'mime_type'],
                'FileDeleteComplete' => ['filename', 'archive_name', 'reason'],
                'FileUndeleteComplete' => ['versions', 'reason'],
                'RenameUserComplete' => ['user_id', 'old_name', 'new_name', 'new_user_url'],
                'LogException' => ['class', 'detail'],
            ];
            $this->assertSame($fields[$payload['hook']], array_keys($payload['data']));
            $this->assertArrayNotHasKey('diff', $payload);
            $this->assertArrayNotHasKey('diff', $payload['data']);
            $this->assertStringNotContainsString('File description', $request['options']['postData']);
        }
        $this->assertCount(count($discord), array_filter(
            $requests, static fn ($request) => str_contains($request['options']['postData'], '"schema_version":1')
        ));
        return $discord;
    }
}
