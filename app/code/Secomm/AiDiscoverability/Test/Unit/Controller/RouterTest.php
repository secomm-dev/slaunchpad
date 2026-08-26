<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Route\ConfigInterface;
use Magento\Framework\App\Router\ActionList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Controller\Router;

/**
 * Root-path routing contract: only GET/HEAD /llms.txt is matched; other
 * methods fall through to no-route (deterministic 404).
 *
 * @covers \Secomm\AiDiscoverability\Controller\Router
 */
class RouterTest extends TestCase
{
    /**
     * @var ActionFactory|MockObject
     */
    private $actionFactory;

    /**
     * @var ActionList|MockObject
     */
    private $actionList;

    /**
     * @var ConfigInterface|MockObject
     */
    private $routeConfig;

    /**
     * @var Router
     */
    private $router;

    protected function setUp(): void
    {
        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->actionList = $this->createMock(ActionList::class);
        $this->routeConfig = $this->createMock(ConfigInterface::class);

        $this->routeConfig->method('getModulesByFrontName')->willReturn(['Secomm_AiDiscoverability']);
        $this->actionList->method('get')->willReturn('ActionClass');

        $this->router = new Router(
            $this->actionFactory,
            $this->actionList,
            $this->routeConfig
        );
    }

    private function request(string $path, string $method): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getMethod')->willReturn($method);

        return $request;
    }

    public function testMatchesGetAndHeadOnly(): void
    {
        $action = $this->createMock(ActionInterface::class);
        $this->actionFactory->method('create')->willReturn($action);

        $this->assertSame($action, $this->router->match($this->request('/llms.txt', 'GET')));
        $this->assertSame($action, $this->router->match($this->request('/llms.txt', 'HEAD')));
    }

    public function testUnsupportedMethodsAreNotMatched(): void
    {
        foreach (['POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->assertNull($this->router->match($this->request('/llms.txt', $method)), $method);
        }
    }

    public function testOtherPathsAreNotMatched(): void
    {
        $this->assertNull($this->router->match($this->request('/llms.txt/full', 'GET')));
        $this->assertNull($this->router->match($this->request('/llms/index/index', 'GET')));
    }
}
