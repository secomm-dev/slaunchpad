<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command;

interface ResultInterface
{
    /**
     * Returns result interpretation
     *
     * @return array
     */
    public function get();
}
