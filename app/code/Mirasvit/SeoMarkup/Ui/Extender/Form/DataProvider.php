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


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Ui\Extender\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Model\ResourceModel\Extender\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    private $context;

    private $dataPersistor;

    public function __construct(
        ContextInterface       $context,
        CollectionFactory      $collectionFactory,
        DataPersistorInterface $dataPersistor,
        string                 $name,
        string                 $primaryFieldName,
        string                 $requestFieldName,
        array                  $meta = [],
        array                  $data = []
    ) {
        $this->context       = $context;
        $this->collection    = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        $data = [];

        $extenderId = (int)$this->context->getRequestParam(ExtenderInterface::REQUEST_PARAM_ID);

        $items = $this->collection
            ->addFieldToFilter(ExtenderInterface::EXTENDER_ID, ['eq' => $extenderId])
            ->getItems();

        foreach ($items as $item) {
            $data[$item->getId()] = $item->getData();
        }

        $restoredData = $this->dataPersistor->get(ExtenderInterface::DATA_PERSISTOR_KEY);
        if ($restoredData) {
            $restoredExtenderId             = $restoredData[ExtenderInterface::EXTENDER_ID] ?? 0;
            $data[(int)$restoredExtenderId] = $restoredData;
        }

        return $data;
    }
}
