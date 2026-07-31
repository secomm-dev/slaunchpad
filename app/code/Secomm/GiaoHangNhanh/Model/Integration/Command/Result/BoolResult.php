<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Command\Result;

use Secomm\GiaoHangNhanh\Model\Integration\Command\ResultInterface;

/**
 * Container for array that should be returned as command result.
 *
 * @api
 */
class BoolResult implements ResultInterface
{
    /**
     * @var array
     */
    private $result;

    /**
     * Constructor
     *
     * @param bool $result
     */
    public function __construct($result = true)
    {
        $this->result = $result;
    }

    /**
     * Returns result interpretation
     *
     * @return mixed
     */
    public function get()
    {
        return (bool) $this->result;
    }
}
