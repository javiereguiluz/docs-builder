<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\CI;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UrlChecker
{
    private $invalidUrls = [];
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create(['timeout' => 10]);
    }

    public function checkUrl(string $url): void
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $statusCode = $response->getStatusCode();
        } catch (HttpExceptionInterface $e) {
            $statusCode = 0;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->invalidUrls[] = [
                'url' => $url,
                'statusCode' => $statusCode,
            ];
        }
    }

    public function getInvalidUrls(): array
    {
        return $this->invalidUrls;
    }
}
