<?php
/*
 * TASK-JZXM66 — base for the City/Area admin self-service actions (options feed + downloads).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Launchpad\MageplazaTableRate\Model\Adminhtml\CsvBuilder;

/**
 * Reuses Mageplaza's ACL resource — the self-service actions extend the same "Shipping
 * Methods" grant (there is no Launchpad-specific permission to configure).
 */
abstract class City extends Action
{
    public const ADMIN_RESOURCE = 'Mageplaza_TableRateShipping::method';

    private readonly ?RawFactory $resultRawFactory;

    public function __construct(
        Context $context,
        // Optional by design: only the CSV download children use it. Magento DI still injects
        // the factory; the optionality keeps the Options action's parent call compiler-clean.
        ?RawFactory $resultRawFactory = null
    ) {
        $this->resultRawFactory = $resultRawFactory;
        parent::__construct($context);
    }

    /**
     * UTF-8 (BOM) CSV attachment response built from the given content.
     */
    protected function csvDownload(string $filename, string $content): Raw
    {
        /** @var Raw $result */
        $result = $this->resultRawFactory->create();
        $result->setContents(CsvBuilder::UTF8_BOM . $content);
        $result->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $result->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);

        return $result;
    }
}
