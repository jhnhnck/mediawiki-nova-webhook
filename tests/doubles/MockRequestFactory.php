<?php
/**
 * NovaDiscord - HttpRequestFactory testing double
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

namespace MediaWiki\Extension\NovaDiscord\Tests\Doubles;

use MediaWiki\Http\HttpRequestFactory;

class MockRequestFactory extends HttpRequestFactory {
    private array $captured = [];
    private array $responses = [];

    public function create($url, array $options = [], $caller = __METHOD__): MockRequest {
        return new MockRequest($url, $options, $caller, $this->captured, array_shift($this->responses) ?? 200);
    }

    public function getCaptured(): array {
        return $this->captured;
    }

    public function queueResponse(int|\Throwable $response): void {
        $this->responses[] = $response;
    }

    public function reset(): void {
        $this->captured = [];
    }
}
