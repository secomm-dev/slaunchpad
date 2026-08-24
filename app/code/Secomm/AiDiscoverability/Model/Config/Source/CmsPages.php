<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Active CMS pages as config multiselect options.
 */
class CmsPages implements OptionSourceInterface
{
    /**
     * @var PageCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var array|null
     */
    private $options;

    /**
     * @param PageCollectionFactory $collectionFactory CMS page collection factory
     */
    public function __construct(PageCollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Active CMS pages as multiselect options, sorted by title.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('title', $collection::SORT_ORDER_ASC);

        $this->options = [];
        foreach ($collection as $page) {
            $title = (string) $page->getTitle();
            $this->options[] = [
                'value' => (string) $page->getId(),
                'label' => $title !== '' ? $title : (string) $page->getIdentifier(),
            ];
        }

        return $this->options;
    }
}
