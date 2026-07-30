<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\SeoSitemap\Model\Pager;

use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Mirasvit\SeoSitemap\Model\Config;

class Collection extends DataCollection
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var int|null
     */
    protected $pageSize;

    /**
     * @var int|null
     */
    protected $currentPage;

    /**
     * @var array
     */
    protected $collection = [];

    public function __construct(
        EntityFactoryInterface $entityFactory,
        Config                 $config
    ) {
        $this->config = $config;

        parent::__construct($entityFactory);
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return (int)$this->pageSize;
    }

    /**
     * @return int
     */
    public function getLastPageNumber()
    {
        return (int)ceil(count($this->collection) / max(1, $this->getSize()));
    }

    /**
     * @return int
     */
    public function getLastPageNum()
    {
        return $this->getLastPageNumber();
    }

    /**
     * @param int $displacement
     * @return int
     */
    public function getCurPage($displacement = 0)
    {
        $page = (int)$this->currentPage;
        if ($displacement == 0) {
            return $page;
        }
        if ($page + $displacement < 1) {
            return 1;
        } elseif ($page + $displacement > $this->getLastPageNumber()) {
            return $this->getLastPageNumber();
        } else {
            return $page + $displacement;
        }
    }

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setPageSize($size)
    {
        $this->pageSize = (int)$size;

        return $this;
    }

    /**
     * @param int $page
     *
     * @return $this
     */
    public function setCurPage($page)
    {
        $this->currentPage = (int)$page;

        return $this;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->collection);
    }

    /**
     * @param array $collection
     *
     * @return void
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return array|\Traversable
     */
    public function getCollection()
    {
        $collection = $this->collection;
        if ($this->getSize() > 0) {
            $limit      = $this->getSize();
            $offset     = ((int)$this->getCurPage() - 1) * $limit;
            $items      = is_array($collection) ? $collection : iterator_to_array($collection);
            $collection = array_slice($items, $offset, $limit);
        }

        return $collection;
    }
}
