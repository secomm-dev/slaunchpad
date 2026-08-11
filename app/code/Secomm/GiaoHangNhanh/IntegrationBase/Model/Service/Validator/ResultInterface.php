<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator;

use Magento\Framework\Phrase;

/**
 * Interface ResultInterface
 * @package Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator
 */
interface ResultInterface
{
    /**
     * Returns validation result
     *
     * @return bool
     */
    public function isValid();

    /**
     * Returns list of fails description
     *
     * @return Phrase[]
     */
    public function getFailsDescription();

    /**
     * Returns list of error codes.
     *
     * @return string[]
     */
    public function getErrorCodes();
}
