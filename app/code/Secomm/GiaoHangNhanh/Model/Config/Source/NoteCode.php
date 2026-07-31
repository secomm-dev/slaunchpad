<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class NoteCode
 *
 * @package Secomm\GiaoHangNhanh\Model\Config\Source
 */
class NoteCode implements ArrayInterface
{
    const ALLOW_TRYING = 'CHOTHUHANG';
    const ALLOW_CHECKING_NOT_TRYING = 'CHOXEMHANGKHONGTHU';
    const NOT_ALLOW_CHECKING = 'KHONGCHOXEMHANG';

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::ALLOW_TRYING, 'label' => __('Allow trying item')],
            ['value' => self::ALLOW_CHECKING_NOT_TRYING, 'label' => __('Allow checking item, but not trying')],
            ['value' => self::NOT_ALLOW_CHECKING, 'label' => __('Don\'t allow checking item')]
        ];
    }
}
