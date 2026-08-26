<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Router\ActionList;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Controller\BadRequest;
use Secomm\AiCommerce\Controller\MethodNotAllowed;
use Secomm\AiCommerce\Controller\Router;
use Secomm\AiCommerce\Model\Config;

class RouterTest extends TestCase
{
    /**
     * @var ActionFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $actionFactory;

    /**
     * @var ActionList|\PHPUnit\Framework\MockObject\MockObject
     */
    private $actionList;

    /**
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var Router
     */
    private $router;

    protected function setUp(): void
    {
        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->actionList = $this->createMock(ActionList::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getEndpointPath')->willReturn('ai');

        $this->router = new Router($this->actionFactory, $this->actionList, $this->config);
    }

    public function testIgnoresForeignPaths(): void
    {
        $this->assertNull($this->router->match($this->request('/catalogsearch/result', 'GET')));
    }

    public function testUnmatchedAiTailFallsToNoRoute(): void
    {
        $this->assertNull($this->router->match($this->request('/ai/anything/else', 'GET')));
        $this->assertNull($this->router->match($this->request('/ai/graphql', 'GET')));
    }

    public function testNonGetHeadGets405Action(): void
    {
        $this->actionFactory->expects($this->once())->method('create')->with(MethodNotAllowed::class);
        $this->router->match($this->request('/ai/store', 'POST'));
    }

    public function testOversizedQueryStringGets400Action(): void
    {
        $this->actionFactory->expects($this->once())->method('create')->with(BadRequest::class);
        $this->router->match($this->request('/ai/store', 'GET', str_repeat('a', 513)));
    }

    public function testProductsRouteSetsSkuParam(): void
    {
        $request = $this->request('/ai/products/linen-shirt', 'GET');
        $sku = null;
        $request->method('setParam')->willReturnCallback(
            function ($key, $value) use (&$sku) {
                if ($key === 'sku') {
                    $sku = $value;
                }

                return null;
            }
        );
        $action = $this->createMock(ActionInterface::class);

        $this->actionList->method('get')->willReturn(get_class($action));
        $this->actionFactory->method('create')->willReturn($action);

        $this->router->match($request);

        $this->assertSame('linen-shirt', $sku);
    }

    public function testCustomBasePathRoutesAndRetiresDefault(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getEndpointPath')->willReturn('agent-api');
        $router = new Router($this->actionFactory, $this->actionList, $config);

        $action = $this->createMock(ActionInterface::class);
        $this->actionList->method('get')->willReturn(get_class($action));
        $this->actionFactory->method('create')->willReturn($action);

        // New path routes…
        $matched = $router->match($this->request('/agent-api/store', 'GET'));
        $this->assertSame($action, $matched);

        // …the old default path no longer matches (single routing authority,
        // no standard frontName fallback).
        $this->assertNull($router->match($this->request('/ai/store', 'GET')));
    }

    public function testMultiSegmentPrefixDoesNotMatchPrefixOfString(): void
    {
        // "ai" must not match "ai-extra/store" — the boundary is enforced.
        $this->assertNull($this->router->match($this->request('/ai-extra/store', 'GET')));
    }

    /**
     * Build a request mock.
     *
     * @param string $pathInfo path info
     * @param string $method HTTP method
     * @param string $queryString raw query string
     * @return HttpRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    private function request(string $pathInfo, string $method, string $queryString = '')
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getMethod')->willReturn($method);
        $request->method('getServer')->with('QUERY_STRING', '')->willReturn($queryString);

        return $request;
    }
}
