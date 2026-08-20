<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class PaymentType
 *
 * @package Secomm\GiaoHangNhanh\Model\Config\Source
 */
class PaymentType implements ArrayInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Shop/Seller')], ['value' => 2, 'label' => __('Buyer/Consignee')]];
    }
}
