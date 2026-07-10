<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Data;

final readonly class ResolvedExceptionReportWebhookEndpointData
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $address,
    ) {}

    public function hostHeader(): string
    {
        return $this->port === 443 ? $this->host : sprintf('%s:%d', $this->host, $this->port);
    }

    public function curlResolveEntry(): string
    {
        return sprintf('%s:%d:%s', $this->host, $this->port, $this->address);
    }
}
