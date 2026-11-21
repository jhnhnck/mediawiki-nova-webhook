<?php
// SPDX-License-Identifier: MIT

namespace MediaWiki\Extension\NovaDiscord\Tests\Doubles;

use StatusValue;
use Throwable;

class MockRequest {
    private array $headers = [];

    public function __construct(
        private string $url,
        private array $options,
        private string $caller,
        private array &$sink,
        private int|Throwable $response
    ) {
    }

    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    public function execute(): StatusValue {
        $this->sink[] = [
            'url' => $this->url,
            'method' => $this->options['method'] ?? 'GET',
            'options' => $this->options,
            'headers' => $this->headers,
            'caller' => $this->caller,
        ];
        if ($this->response instanceof Throwable) {
            throw $this->response;
        }
        return $this->response >= 200 && $this->response < 300
            ? StatusValue::newGood() : StatusValue::newFatal('HTTP failure');
    }

    public function getStatus(): int {
        return $this->response instanceof Throwable ? 0 : $this->response;
    }

    public function getContent(): string {
        return '';
    }
}
