<?php
/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model;

use Secomm\Ahamove\Api\TracerInterface;
use Secomm\Ahamove\Traits\ApiRespond;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Registry;

class Tracer
{
    use ApiRespond;

    /**
     * @param Registry $registry
     */
    public function __construct(
        protected Registry $registry
    )
    {
    }

    /**
     * @param TracerInterface $tracer
     * @return TracerInterface
     */
    public function beforeSend(TracerInterface $tracer): TracerInterface
    {
        return $tracer;
    }

    /**
     * @param TracerInterface $tracer
     * @param $respond
     * @return TracerInterface
     */
    public function afterSend(TracerInterface $tracer, $respond = []): TracerInterface
    {
        return $tracer;
    }
}
