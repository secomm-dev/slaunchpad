<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\CodRisk\Model\ResourceModel\CodRiskList as CodRiskListResource;

/**
 * Phone list record — single table holds both list types (D-10).
 */
class CodRiskList extends AbstractModel
{
    public const LIST_TYPE_BLOCK = 'BLOCK';
    public const LIST_TYPE_ALLOW = 'ALLOW';
    public const SOURCE_ADMIN = 'ADMIN';
    public const SOURCE_ORDER = 'ORDER';
    public const SOURCE_IMPORT = 'IMPORT';

    protected $_cacheTag = 'secomm_cod_risk_list';
    protected $_eventPrefix = 'secomm_cod_risk_list';

    protected function _construct(): void
    {
        $this->_init(CodRiskListResource::class);
    }
}