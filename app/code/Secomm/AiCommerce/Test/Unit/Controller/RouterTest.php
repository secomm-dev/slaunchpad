<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Router\ActionList;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Controller\BadRequest;
use Secomm\AiCommerce\Controller\MethodNotAllowed;
use Secomm\AiCommerce\Controller\Router;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\PathGuard;
use Secomm\AiCommerce\Model\StoreContext\Resolver;

/**
 * BUG-D4QK1Q: the router must resolve the TARGET store view first (real
 * Resolver + real PathGuard; only the boundary collaborators — store
 * registry/manager and the scoped config reader — are mocked), then run
 * store-scoped enabled/base-path checks. Fixture: Default store id 1 →
 * path "ai"; store view id 3 ("vi_vn") → path "agent".
 */
class RouterTest extends TestCase
{
    private const DEFAULT_ID = 1;
    private const VI_VN_ID = 3;

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
     * @var StoreRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeRepository;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManager;

    /**
     * @var StoreInterface[]|\PHPUnit\Framework\MockObject\MockObject[]
     */
    private $stores;

    /**
     * @var int[] store ids passed to Config::isEnabled()
     */
    private $enabledCalls = [];

    /**
     * @var int[] store ids passed to Config::getEndpointPath()
     */
    private $pathCalls = [];

    /**
     * @var int[] store ids the config fixture reports as disabled
     */
    private $disabledStoreIds = [];

    protected function setUp(): void
    {
        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->actionList = $this->createMock(ActionList::class);

        $this->stores = [
            self::DEFAULT_ID => $this->store(self::DEFAULT_ID),
            self::VI_VN_ID => $this->store(self::VI_VN_ID),
        ];

        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->storeRepository->method('getActiveStoreByCode')->willReturnCallback(
            fn (string $code): StoreInterface => $code === 'vi_vn'
                ? $this->stores[self::VI_VN_ID]
                : throw new \Magento\Framework\Exception\NoSuchEntityException()
        );

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getDefaultStoreView')->willReturn($this->stores[self::DEFAULT_ID]);

        $this->enabledCalls = [];
        $this->pathCalls = [];
        $this->disabledStoreIds = [];

        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturnCallback(function (?int $id = null): bool {
            $this->enabledCalls[] = (int) $id;

            return !in_array((int) $id, $this->disabledStoreIds, true);
        });
        $this->config->method('getEndpointPath')->willReturnCallback(function (?int $id = null): string {
            $this->pathCalls[] = (int) $id;

            return (int) $id === self::VI_VN_ID ? 'agent' : 'ai';
        });
    }

    public function testDefaultPathNoParamMatchesDefaultStore(): void
    {
        $this->assertActionCreated();
        $matched = $this->router()->match($this->request('/ai/store', 'GET'));

        $this->assertNotNull($matched);
    }

    public function testConfigChecksUseResolvedTargetStoreId(): void
    {
        $this->assertActionCreated();

        $this->assertNotNull(
            $this->router()->match($this->request('/agent/store', 'GET', '', 'vi_vn'))
        );

        $this->assertContains(self::VI_VN_ID, $this->enabledCalls, 'enabled check must use the resolved target store id');
        $this->assertContains(self::VI_VN_ID, $this->pathCalls, 'path lookup must use the resolved target store id');
    }

    public function testStoreOverridePathMatchesWithStoreParam(): void
    {
        $this->assertActionCreated();

        $matched = $this->router()->match($this->request('/agent/store', 'GET', '', 'vi_vn'));

        $this->assertNotNull($matched);
    }

    public function testDefaultPathWithStoreOverrideParamIsRejected(): void
    {
        // wrong path (/ai) + valid ?store=vi_vn => NO MATCH (strict boundary).
        $this->assertNull($this->router()->match($this->request('/ai/store', 'GET', '', 'vi_vn')));
    }

    public function testOverriddenPathWithoutStoreParamIsRejected(): void
    {
        // No selector => default store => default path only.
        $this->assertNull($this->router()->match($this->request('/agent/store', 'GET')));
    }

    public function testDisabledTargetStoreIsNotRouted(): void
    {
        $this->disabledStoreIds = [self::VI_VN_ID];

        $this->assertNull($this->router()->match($this->request('/agent/store', 'GET', '', 'vi_vn')));
    }

    public function testTwoStoresWithDifferentPathsDoNotCrossMatch(): void
    {
        $this->assertActionCreated();
        // default store context cannot use vi_vn's path…
        $this->assertNull($this->router()->match($this->request('/agent/store', 'GET')));
        // …and vi_vn cannot use the default store's path.
        $this->assertNull($this->router()->match($this->request('/ai/categories', 'GET', '', 'vi_vn')));
        // each own path still routes.
        $this->assertNotNull($this->router()->match($this->request('/ai/categories', 'GET')));
        $this->assertNotNull($this->router()->match($this->request('/agent/categories', 'GET', '', 'vi_vn')));
    }

    public function testInvalidStoreParamKeepsDefaultPathAdmissionOnly(): void
    {
        // Invalid selector: admission falls back to the default store's path
        // so the controller can answer the documented 400 invalid_store.
        $this->assertActionCreated();
        $this->assertNotNull($this->router()->match($this->request('/ai/store', 'GET', '', 'not a code!')));
        $this->assertNull($this->router()->match($this->request('/agent/store', 'GET', '', 'nope')));
    }

    public function testUnmatchedTailFallsToNoRoute(): void
    {
        $this->assertNull($this->router()->match($this->request('/ai/anything/else', 'GET')));
        $this->assertNull($this->router()->match($this->request('/ai/graphql', 'GET')));
    }

    public function testNonGetHeadGets405Action(): void
    {
        $this->actionFactory->expects($this->once())->method('create')->with(MethodNotAllowed::class);
        $this->router()->match($this->request('/ai/store', 'POST'));
    }

    public function testOversizedQueryStringGets400Action(): void
    {
        $this->actionFactory->expects($this->once())->method('create')->with(BadRequest::class);
        $this->router()->match($this->request('/ai/store', 'GET', str_repeat('a', 513)));
    }

    public function testProductsRouteSetsSkuParamOnStoreOverridePath(): void
    {
        $request = $this->request('/agent/products/linen-shirt', 'GET', 'store=vi_vn', 'vi_vn');
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

        $this->router()->match($request);

        $this->assertSame('linen-shirt', $sku);
    }

    public function testBoundaryPrefixDoesNotMatchPrefixOfString(): void
    {
        $this->assertNull($this->router()->match($this->request('/ai-extra/store', 'GET')));
    }

    private function router(): Router
    {
        return new Router(
            $this->actionFactory,
            $this->actionList,
            $this->config,
            new Resolver($this->storeRepository, $this->storeManager),
            new PathGuard($this->config)
        );
    }

    private function assertActionCreated(): void
    {
        $action = $this->createMock(ActionInterface::class);
        $this->actionList->method('get')->willReturn(get_class($action));
        $this->actionFactory->method('create')->willReturn($action);
    }

    private function store(int $id): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);

        return $store;
    }

    /**
     * Build a request mock.
     *
     * @param string $pathInfo path info
     * @param string $method HTTP method
     * @param string $queryString raw query string
     * @param string|null $storeParam raw `store` query parameter value
     * @return HttpRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    private function request(string $pathInfo, string $method, string $queryString = '', ?string $storeParam = null)
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getMethod')->willReturn($method);
        $request->method('getServer')->with('QUERY_STRING', '')->willReturn($queryString);
        $request->method('getParam')->willReturnCallback(
            fn (string $key, $default = null) => $key === Resolver::PARAM_STORE ? $storeParam : $default
        );

        return $request;
    }
}
