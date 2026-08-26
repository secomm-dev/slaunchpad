<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Controller\Products;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Model\Cache\ResponseCache;
use Secomm\AiCommerce\Model\Config;
use Secomm\AiCommerce\Model\StoreContext\Resolver as StoreResolver;
use Secomm\AiCommerce\Service\Catalog\ProductFetcher;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Controller\Products\View;
use Secomm\AiCommerce\Service\Response\Responder;

/**
 * SPEC-TASK-AIC-PDC1: product detail served through the shared ResponseCache.
 */
class ViewTest extends TestCase
{
    /**
     * @var StoreResolver&MockObject
     */
    private $storeResolver;

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var ResponseCache&MockObject
     */
    private $responseCache;

    /**
     * @var ProductFetcher&MockObject
     */
    private $productFetcher;

    /**
     * @var Responder&MockObject
     */
    private $responder;

    /**
     * @var HttpRequest&MockObject
     */
    private $request;

    /**
     * @var StoreInterface&MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->storeResolver = $this->createMock(StoreResolver::class);
        $this->config = $this->createMock(Config::class);
        $this->responseCache = $this->createMock(ResponseCache::class);
        $this->productFetcher = $this->createMock(ProductFetcher::class);
        $this->responder = $this->createMock(Responder::class);
        $this->request = $this->createMock(HttpRequest::class);

        $this->store = $this->createMock(StoreInterface::class);
        $this->store->method('getId')->willReturn(3);
        $this->storeResolver->method('resolve')->willReturn($this->store);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getCacheLifetime')->willReturn(3600);
    }

    public function testColdMissExecutesPipelineAndPopulatesCache(): void
    {
        $dto = ['sku' => 'ABC', 'name' => 'Product ABC'];
        $this->request->method('getParam')->willReturnMap([
            ['store', null, 'vi_vn'],
            ['sku', '', 'ABC'],
        ]);
        $this->responseCache->expects($this->once())->method('load')
            ->with(3, 'product', ['sku' => 'ABC'])
            ->willReturn(null);
        $this->productFetcher->expects($this->once())->method('fetch')
            ->with('ABC', $this->store)
            ->willReturn($dto);
        $this->responseCache->expects($this->once())->method('save')
            ->with($dto, 3, 'product', ['sku' => 'ABC'], 3600);
        $this->responder->expects($this->once())->method('json')->with($dto, 3600)
            ->willReturn($this->resultMock());

        $this->controller()->execute();
    }

    public function testWarmHitSkipsPipelineAndSave(): void
    {
        $dto = ['sku' => 'ABC', 'name' => 'Product ABC'];
        $this->request->method('getParam')->willReturnMap([
            ['store', null, 'vi_vn'],
            ['sku', '', 'ABC'],
        ]);
        $this->responseCache->method('load')->willReturn($dto);
        $this->productFetcher->expects($this->never())->method('fetch');
        $this->responseCache->expects($this->never())->method('save');
        $this->responder->expects($this->once())->method('json')->with($dto, 3600)
            ->willReturn($this->resultMock());

        $this->controller()->execute();
    }

    public function testCacheLifetimeZeroStillRespondsWithZero(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getCacheLifetime')->willReturn(0);
        $this->request->method('getParam')->willReturnMap([
            ['store', null, 'vi_vn'],
            ['sku', '', 'ABC'],
        ]);
        $this->responseCache->method('load')->willReturn(null);
        $this->productFetcher->method('fetch')->willReturn(['sku' => 'ABC']);
        // ResponseCache::save is a no-op for lifetime 0 (its own contract), so
        // the controller still delegates; the responder receives lifetime 0.
        $this->responder->expects($this->once())->method('json')->with(['sku' => 'ABC'], 0)
            ->willReturn($this->resultMock());

        $this->controller()->execute();
    }

    public function testMalformedSkuIsNotCachedAndStaysError(): void
    {
        $this->request->method('getParam')->willReturnMap([
            ['store', null, 'vi_vn'],
            ['sku', '', '<script>'],
        ]);
        $this->responseCache->method('load')->willReturn(null);
        $this->productFetcher->method('fetch')
            ->willThrowException(new InvalidParameterException(__('Invalid request parameters.')));
        $this->responseCache->expects($this->never())->method('save');
        $this->responder->expects($this->once())->method('error')
            ->willReturn($this->resultMock());

        $this->controller()->execute();
    }

    public function testDisabledStoreShortCircuitsBeforeCacheIo(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(false);
        $this->request->method('getParam')->willReturnMap([
            ['store', null, 'vi_vn'],
            ['sku', '', 'ABC'],
        ]);
        $this->responseCache->expects($this->never())->method('load');
        $this->productFetcher->expects($this->never())->method('fetch');
        $this->responder->expects($this->once())->method('error')
            ->with(new NotFoundException(__('Resource not found.')))
            ->willReturn($this->resultMock());

        $this->controller()->execute();
    }

    private function controller(): View
    {
        return new View(
            $this->storeResolver,
            $this->config,
            $this->responseCache,
            $this->productFetcher,
            $this->responder,
            $this->request
        );
    }

    private function resultMock(): ResultInterface
    {
        return $this->createMock(ResultInterface::class);
    }
}
