<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\NovaDiscord\DiscordAlert;
use MediaWiki\Extension\NovaDiscord\DiscordRenderer;
use MediaWiki\Extension\NovaDiscord\NovaDiscordConfig;
use MediaWiki\Extension\NovaDiscord\WebhookEvent;
use MediaWiki\Utils\MWTimestamp;
use RuntimeException;

/**
 * @group Database
 * @covers \MediaWiki\Extension\NovaDiscord\WebhookEvent
 * @covers \MediaWiki\Extension\NovaDiscord\DiscordRenderer
 * @covers \MediaWiki\Extension\NovaDiscord\DiscordAlert::sendEvent
 * @covers \MediaWiki\Extension\NovaDiscord\NovaDiscordConfig::getDestinationsForHook
 */
class StructuredEventTest extends NovaDiscordIntegrationTestCase {
    protected function setUp(): void {
        parent::setUp();
        $hooks = ['LogException', 'PageSaveComplete', 'PageDeleteComplete'];
        $this->overrideConfigValue('DiscordWebhooks', [
            ['url' => getenv('NOVA_WEBHOOK_URL'), 'hooks' => $hooks],
            ['url' => 'https://events.example.test/hook', 'format' => 'json', 'hooks' => $hooks],
        ]);
    }

    public function testRealEditFansOutFromSharedData(): void {
        $user = $this->getTestUser();
        MWTimestamp::setFakeTime('20260102030405');
        $status = $this->editPage('NovaStructured', 'Private raw body', 'Résumé `@everyone`', 0, $user->getAuthority());
        $this->assertStatusGood($status);
        $this->assertCount(1, $this->getDiscordRequests());
        $requests = $this->mockHttpFactory->getCaptured();
        $this->assertCount(2, $requests);
        $json = json_decode($requests[1]['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://events.example.test/hook', $requests[1]['url']);
        $this->assertSame('page.created', $json['event']);
        $this->assertSame('PageSaveComplete', $json['hook']);
        $this->assertSame('2026-01-02T03:04:05Z', $json['timestamp']);
        $this->assertSame($user->getUser()->getId(), $json['actor']['id']);
        $this->assertSame($status->getNewRevision()->getId(), $json['data']['revision']['id']);
        $this->assertSame('Résumé `@everyone`', $json['data']['summary']);
        $this->assertStringNotContainsString('Private raw body', $requests[1]['options']['postData']);
        MWTimestamp::setFakeTime(false);
    }

    public function testDeletionUsesOperationTime(): void {
        MWTimestamp::setFakeTime('20200101000000');
        $this->editPage('NovaDeleteTime', 'Old content');
        $page = $this->getExistingTestPage('NovaDeleteTime');
        MWTimestamp::setFakeTime('20260102030405');
        $this->mockHttpFactory->reset();
        $this->deletePage($page, 'Deletion time', $this->getTestSysop()->getAuthority());
        $this->assertCount(1, $this->getDiscordRequests());
        $requests = $this->mockHttpFactory->getCaptured();
        $json = json_decode($requests[1]['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026-01-02T03:04:05Z', $json['timestamp']);
        $this->assertSame('page.deleted', $json['event']);
        MWTimestamp::setFakeTime(false);
    }

    /** @dataProvider provideFailures */
    public function testFailedDestinationDoesNotStopDelivery(bool $throws): void {
        $this->mockHttpFactory->queueResponse($throws ? new RuntimeException('Secret endpoint') : 503);
        $this->mockHttpFactory->queueResponse(204);
        $this->deliver($this->event());
        $this->assertCount(2, $this->mockHttpFactory->getCaptured());
    }

    public static function provideFailures(): array {
        return ['http status' => [false], 'transport exception' => [true]];
    }

    public function testJsonDoesNotUseDiscordContentLimit(): void {
        $event = $this->event(str_repeat('Résumé ', 1000));
        $before = $event->toArray();
        $discord = (new DiscordRenderer(500))->render($event);
        $this->assertLessThanOrEqual(2000, mb_strlen($discord['content']));
        $this->assertSame($before, $event->toArray());
        $this->deliver($event);
        $requests = $this->mockHttpFactory->getCaptured();
        $this->assertGreaterThan(2000, strlen($requests[1]['options']['postData']));
        $json = json_decode($requests[1]['options']['postData'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($before, $json);
    }

    public function testMalformedEncodingDoesNotSendInvalidJson(): void {
        $this->deliver($this->event("\xB1\x31"));
        $this->assertCount(0, $this->mockHttpFactory->getCaptured());
    }

    public function testInvalidFormatsAndUrlsAreExcluded(): void {
        $this->overrideConfigValue('DiscordWebhooks', [
            ['url' => 'https://events.example.test/invalid', 'format' => 'xml'],
            ['url' => 'https://events.example.test/null', 'format' => null],
            ['url' => 'http://events.example.test/insecure', 'format' => 'json'],
            ['url' => ['malformed']],
            ['url' => 'https://events.example.test/excluded', 'hooks' => ['PageSaveComplete']],
            ['url' => 'https://events.example.test/valid', 'format' => 'json', 'hooks' => ['LogException']],
        ]);
        $this->deliver($this->event());
        $requests = $this->mockHttpFactory->getCaptured();
        $this->assertCount(1, $requests);
        $this->assertSame('https://events.example.test/valid', $requests[0]['url']);
    }

    public function testCoreObjectsCannotEnterTheEventContract(): void {
        $this->expectException(\InvalidArgumentException::class);
        new WebhookEvent('LocalUserCreated', time(), null, null, ['user' => $this->getTestUser()->getUser()]);
    }

    public function testDiscordTruncationPreservesDiffFence(): void {
        $event = new WebhookEvent(
            'LogException', time(), null, null, ['class' => 'Example', 'detail' => 'Example'],
            "```diff\n" . str_repeat("+ changed\n", 400) . '```'
        );
        $payload = (new DiscordRenderer(500))->render($event);
        $this->assertSame(2000, mb_strlen($payload['content']));
        $this->assertStringEndsWith("\n```", $payload['content']);
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
        $this->assertArrayNotHasKey('diff', $event->toArray());
    }

    private function event(string $detail = 'An exception'): WebhookEvent {
        return new WebhookEvent('LogException', 1767323045, null, null, [
            'class' => RuntimeException::class, 'detail' => $detail,
        ]);
    }

    private function deliver(WebhookEvent $event): void {
        $services = $this->getServiceContainer();
        $config = new NovaDiscordConfig(new ServiceOptions(
            NovaDiscordConfig::CONSTRUCTOR_OPTIONS, $services->getMainConfig()
        ));
        // the transport seam exercises serialization independently of exception eligibility
        $alert = new class(
            $this->mockHttpFactory, $services->getRevisionLookup(), $services->getTitleFactory(),
            $services->getUrlUtils(), $config, $services->getService('NovaDiscord.LoggerSpi')
        ) extends DiscordAlert {
            public function deliver(WebhookEvent $event): void {
                $this->sendEvent($event);
            }
        };
        $alert->deliver($event);
    }
}
