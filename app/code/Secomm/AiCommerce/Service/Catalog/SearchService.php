<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Secomm\AiCommerce\Service\Input\SearchQueryParser;
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
     */
    public function __construct(
        private readonly ProductCollectionFactory $collectionFactory,
        private readonly SearchQueryParser $queryParser,
        private readonly Availability $availability,
        private readonly ProductDto $productDto,
        private readonly SearchResultDto $searchResultDto,
        private readonly PublicUrlResolver $urlResolver
    ) {
    }

    /**
     * Execute a bounded, parsed search and build the DTO response.
     *
     * @param StoreInterface $store resolved store view
     * @param mixed[] $params raw GET parameters (parser enforces the allowlist)
     * @return mixed[] search result DTO array
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

            if ($criteria['q'] !== null) {
                $collection->addSearchFilter($criteria['q']);
            }

            if ($criteria['category_id'] !== null) {
                $collection->addCategoriesFilter(['eq' => [$criteria['category_id']]]);
            }

            if ($criteria['price_min'] !== null) {
                $collection->addFieldToFilter('price', ['gteq' => $criteria['price_min']]);
            }

            if ($criteria['price_max'] !== null) {
                $collection->addFieldToFilter('price', ['lteq' => $criteria['price_max']]);
            }

            foreach ($criteria['filters'] as $attribute => $value) {
                $collection->addFieldToFilter($attribute, ['eq' => $value]);
            }

            $this->applySort($collection, $criteria['sort']);

            $collection->setCurPage($criteria['page']);
            $collection->setPageSize($criteria['page_size']);
            $collection->load();
        } catch (LocalizedException $exception) {
            throw new SearchUnavailableException(__('Search is temporarily unavailable.'));
        }

        $products = array_values(array_filter(
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
            (int) $collection->getSize(),
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
