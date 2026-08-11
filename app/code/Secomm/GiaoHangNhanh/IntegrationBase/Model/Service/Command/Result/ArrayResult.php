<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\Result;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\ResultInterface;

/**
 * Container for array that should be returned as command result.
 *
 * @api
 */
class ArrayResult implements ResultInterface
{

    /**
     * @var array
     */
    private $array;

    /**
     * @param array $array
     */
    public function __construct(array $array = [])
    {
        $this->array = $array;
    }

    /**
     * Returns result interpretation
     *
     * @return array
     */
    public function get()
    {
        return $this->array;
    }
}
