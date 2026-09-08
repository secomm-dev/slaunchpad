<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Customer\Model\Group;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiCommerce\Service\Input\SearchQueryParser;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\Inventory\Availability;
use Secomm\AiCommerce\Service\Response\ProductDto;
use Secomm\AiCommerce\Service\Response\SearchResultDto;
use Secomm\AiCommerce\Service\SearchUnavailableException;
use Secomm\AiCommerce\Service\Url\PublicUrlResolver;

/**
 * Delegating search service over the storefront Fulltext collection.
 *
 * The collection factory resolves (via the Smile Elasticsuite preference,
 * module-elasticsuite-catalog/etc/di.xml:87) to the same collection the
 * storefront layer uses, so keyword/category/price/filter/sort semantics —
 * including the Elasticsuite engine — are inherited, never duplicated.
 * Salability is resolved with ONE batch call for the whole page.
 */
class SearchService
{
    /**
     * The factory argument is the module virtualType
     * secomm_aic_fulltext_collection_factory (etc/frontend/di.xml) built on
     * the real Product\CollectionFactory with instanceName = the storefront
     * Fulltext collection — the injected runtime object IS the storefront
     * fulltext factory; the parent hint keeps setup:di:compile resolvable
     * (the core Fulltext CollectionFactory is a virtualType, not a class).
     *
     * @param ProductCollectionFactory $collectionFactory storefront fulltext collection factory
     * @param SearchQueryParser $queryParser allowlist query parser
     * @param Availability $availability batch salability
     * @param ProductDto $productDto product DTO builder
     * @param SearchResultDto $searchResultDto search result envelope builder
     * @param PublicUrlResolver $urlResolver batch public/canonical URL resolver
     * @param CategoryRepositoryInterface $categoryRepository category resolution for the category filter
     */
    public function __construct(
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly SearchQueryParser $queryParser,
        private readonly Availability $availability,
        private readonly ProductDto $productDto,
        private readonly SearchResultDto $searchResultDto,
        private readonly PublicUrlResolver $urlResolver,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * Execute a bounded, parsed search and build the DTO response.
     *
     * @param StoreInterface $store resolved store view
     * @param mixed[] $params raw GET parameters (parser enforces the allowlist)
     * @return mixed[] search result DTO array
     * @throws InvalidParameterException when the parser or the category filter reject the request
     * @throws SearchUnavailableException when the engine/index cannot serve the query
     */
    public function search(StoreInterface $store, array $params): array
    {
        $storeId = (int) $store->getId();
        $criteria = $this->queryParser->parse($params, $storeId);

        try {
            /** @var FulltextCollection $collection */
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSelect(
                ['name', 'small_image', 'short_description', 'price', 'special_price',
                 'special_from_date', 'special_to_date', 'price_type']
            );
            $collection->setVisibility([Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH]);
            // Guest price context: the Elasticsuite collection reads
            // _productLimitationFilters['customer_group_id'] unguarded when
            // building the nested price sort — without addPriceData a
            // price_asc/price_desc request crashes with an undefined index.
            $collection->addPriceData(Group::NOT_LOGGED_IN_ID, (int) $store->getWebsiteId());

            if ($criteria['q'] !== null) {
                $collection->addSearchFilter($criteria['q']);
            }

            if ($criteria['category_id'] !== null) {
                try {
                    $category = $this->categoryRepository->get((int) $criteria['category_id'], $storeId);
                } catch (NoSuchEntityException $exception) {
                    throw new InvalidParameterException(__('Invalid request parameters.'));
                }
                // Engine-level category constraint — the SQL-oriented
                // addCategoriesFilter is silently ignored by the Elasticsuite
                // collection, leaking the unfiltered catalog.
                $collection->addCategoryFilter($category);
            }

            // ONE combined condition: the collection stores filters keyed by
            // mapped field name, so a second addFieldToFilter('price', ...)
            // would silently overwrite the first bound.
            $priceCondition = array_filter([
                'gteq' => $criteria['price_min'],
                'lteq' => $criteria['price_max'],
            ], static fn ($value): bool => $value !== null);

            if ($priceCondition !== []) {
                $collection->addFieldToFilter('price', $priceCondition);
            }

            foreach ($criteria['filters'] as $attribute => $value) {
                $collection->addFieldToFilter($attribute, ['eq' => $value]);
            }

            $this->applySort($collection, $criteria['sort']);

            $collection->setCurPage($criteria['page']);
            $collection->setPageSize($criteria['page_size']);
            $collection->load();
        } catch (InvalidParameterException $exception) {
            // Parser/category rejections must surface as 400, not be
            // re-wrapped as an engine outage.
            throw $exception;
        } catch (LocalizedException $exception) {
            throw new SearchUnavailableException(__('Search is temporarily unavailable.'));
        }

        // BUG-Q8L5RD: the Elasticsuite runtime can reset the window to page 1
        // when the requested page is beyond the last page (within the parser
        // cap) while the count query keeps reporting the true total. Derive
        // the valid page range from total_count and the requested page_size —
        // never from the collection items — and serve an empty window when
        // the request exceeds it, so the response never maps another page's
        // products under the requested page number.
        $totalCount = (int) $collection->getSize();
        $lastPage = (int) ceil($totalCount / max(1, $criteria['page_size']));
        $products = $criteria['page'] > $lastPage
            ? []
            : array_values(array_filter(
                $collection->getItems(),
                static fn ($product): bool => $product instanceof ProductInterface
            ));

        $statuses = $this->availability->getStatuses(array_map(
            static fn (ProductInterface $product): string => (string) $product->getSku(),
            $products
        ));

        // ONE bounded url_rewrite SELECT for the whole result page (P1.2).
        $urls = $this->urlResolver->resolveMany(
            'product',
            array_map(static fn (ProductInterface $product): int => (int) $product->getId(), $products),
            $store
        );

        $items = [];

        foreach ($products as $product) {
            $items[] = $this->productDto->toSummaryArray(
                $product,
                $store,
                $statuses[(string) $product->getSku()] ?? 'out_of_stock',
                $urls[(int) $product->getId()] ?? ['public_url' => null, 'canonical_url' => null]
            );
        }

        return $this->searchResultDto->toArray(
            $items,
            $totalCount,
            $criteria['page'],
            $criteria['page_size']
        );
    }

    /**
     * Map an allowlisted sort key onto collection ordering.
     *
     * Typed against AbstractDb (not the core Fulltext collection) because
     * the Elasticsuite preference swaps in Smile's own collection class,
     * which does not extend the core Fulltext resource model.
     *
     * @param \Magento\Framework\Data\Collection\AbstractDb $collection target collection
     * @param string $sort allowlisted sort key
     * @return void
     */
    private function applySort(\Magento\Framework\Data\Collection\AbstractDb $collection, string $sort): void
    {
        switch ($sort) {
            case 'price_asc':
                $collection->setOrder('price', 'asc');
                break;
            case 'price_desc':
                $collection->setOrder('price', 'desc');
                break;
            case 'name_asc':
                $collection->setOrder('name', 'asc');
                break;
            case 'name_desc':
                $collection->setOrder('name', 'desc');
                break;
            default:
                // relevance: no explicit order — the fulltext relevance
                // ordering of the engine applies.
                break;
        }
    }
}
