<?php

declare(strict_types=1);

namespace Launchpad\CmsContent\Block\Blog;

use Magefan\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Journal section block (TASK-0NNZCW v4, SLP-213): renders the latest Magefan
 * blog posts as a slider (mobile) / grid (desktop).
 *
 * Options via block directive arguments: count (default 3).
 */
class Journal extends Template
{
    private PostCollectionFactory $postCollectionFactory;

    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        PostCollectionFactory $postCollectionFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->postCollectionFactory = $postCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @return \Magefan\Blog\Model\Post[]
     */
    public function getPosts(): array
    {
        $count = max(1, (int) ($this->getData('count') ?: 3));
        $collection = $this->postCollectionFactory->create();
        $collection->addActiveFilter()
            ->addStoreFilter((int) $this->storeManager->getStore()->getId())
            ->setOrder('publish_time', 'DESC')
            ->setPageSize($count);

        return $collection->getItems();
    }

    /**
     * Plain-text excerpt for the card (short_content, falling back to content).
     */
    public function getExcerpt(object $post, int $limit = 160): string
    {
        $text = strip_tags((string) ($post->getShortContent() ?: $post->getContent()));

        return mb_strlen($text) > $limit
            ? trim(mb_substr($text, 0, $limit)) . '…'
            : trim($text);
    }
}
