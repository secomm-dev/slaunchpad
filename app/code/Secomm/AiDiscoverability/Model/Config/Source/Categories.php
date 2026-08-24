<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Active categories (admin scope) as config multiselect options.
 */
class Categories implements OptionSourceInterface
{
    private const ROOT_CATEGORY_ID = 1;

    /**
     * @var array|null
     */
    private ?array $options = null;

    /**
     * @param CategoryCollectionFactory $collectionFactory category collection factory
     */
    public function __construct(private readonly CategoryCollectionFactory $collectionFactory)
    {
    }

    /**
     * Active non-root categories as multiselect options, sorted by name.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('entity_id', ['neq' => self::ROOT_CATEGORY_ID]);
        $collection->addAttributeToSort('name', $collection::SORT_ORDER_ASC);

        $this->options = [];
        foreach ($collection as $category) {
            $name = (string) $category->getName();
            $this->options[] = [
                'value' => (string) $category->getId(),
                'label' => $name !== '' ? $name : (string) $category->getId(),
            ];
        }

        return $this->options;
    }
}
