<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Controller\Index;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Controller\Index\Index;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\LlmsTxtProvider;

/**
 * HTTP cache semantics of the /llms.txt endpoint: Cache-Control derives from
 * the store-scoped cache lifetime; lifetime 0 advertises no positive TTL and
 * skips ETag/conditional handling.
 *
 * @covers \Secomm\AiDiscoverability\Controller\Index\Index
 */
class IndexTest extends TestCase
{
    private const BODY = "# Store\n> summary\n";

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var LlmsTxtProvider|MockObject
     */
    private $provider;

    /**
     * @var HttpResponse|MockObject
     */
    private $response;

    /**
     * @var HttpRequest|MockObject
     */
    private $request;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var array<string, string>
     */
    private $headers = [];

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(true);

        $this->provider = $this->createMock(LlmsTxtProvider::class);
        $this->provider->method('get')->willReturn(self::BODY);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn('1');
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->response = $this->createMock(HttpResponse::class);
        $this->request = $this->createMock(HttpRequest::class);
    }

    public function testLifetimeDefaultAdvertisesMatchingMaxAge(): void
    {
        $this->config->method('getCacheLifetime')->willReturn(86400);

        $this->controller('GET')->execute();

        $this->assertSame('public, max-age=86400', $this->headers['Cache-Control'] ?? '');
    }

    public function testCustomLifetimeAdvertisesMatchingMaxAge(): void
    {
        $this->config->method('getCacheLifetime')->willReturn(300);

        $this->controller('GET')->execute();

        $this->assertSame('public, max-age=300', $this->headers['Cache-Control'] ?? '');
    }

    public function testLifetimeZeroHasNoPositiveCacheTtlAndNoEtag(): void
    {
        $this->config->method('getCacheLifetime')->willReturn(0);

        $this->controller('GET')->execute();

        $this->assertSame('no-store, no-cache, must-revalidate', $this->headers['Cache-Control'] ?? '');
        $this->assertArrayNotHasKey('ETag', $this->headers);
    }

    public function testConditionalGetMatchesEtagTo304(): void
    {
        $this->config->method('getCacheLifetime')->willReturn(86400);
        $this->request->method('getHeader')->willReturn('"' . sha1(self::BODY) . '"');
        $this->response->expects($this->once())->method('setStatusHeader')->with(304);

        $result = $this->controller('GET')->execute();

        $this->assertSame('"' . sha1(self::BODY) . '"', $this->headers['ETag'] ?? '');
        $this->assertInstanceOf(Raw::class, $result);
    }

    public function testDisabledYieldsPlainResultWithoutBody(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(false);
        $this->response->expects($this->once())->method('setStatusHeader')->with(404);

        $result = $this->controller('GET')->execute();

        $this->assertInstanceOf(Raw::class, $result);
    }

    private function controller(string $method): Index
    {
        $this->request->method('getMethod')->willReturn($method);

        $raw = $this->createMock(Raw::class);
        $raw->method('setHeader')->willReturnCallback(
            function (string $name, string $value) use ($raw): Raw {
                $this->headers[$name] = $value;

                return $raw;
            }
        );
        $raw->method('setContents')->willReturnSelf();

        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->willReturn($raw);

        return new Index(
            $this->config,
            $this->provider,
            $this->storeManager,
            $resultFactory,
            $this->response,
            $this->request
        );
    }
}
