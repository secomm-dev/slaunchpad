<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

/**
 * Base admin controller: single ACL resource (D-13). Controllers stay thin —
 * validate, delegate to Model/Service, respond.
 *
 * Deliberately NOT method-restricted here: GET pages declare
 * HttpGetActionInterface, POST handlers declare HttpPostActionInterface —
 * a GET-only declaration makes Magento 404 every POST (bitten 2026-09-22).
 */
abstract class AbstractAction extends Action
{
    public const ADMIN_RESOURCE = 'Secomm_CodRisk::manage';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }
}
