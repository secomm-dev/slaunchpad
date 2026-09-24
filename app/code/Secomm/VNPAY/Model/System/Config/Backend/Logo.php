<?php
/**
 * Secomm VNPAY — checkout logo upload backend model.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 */
declare(strict_types=1);

namespace Secomm\VNPAY\Model\System\Config\Backend;

use Magento\Config\Model\Config\Backend\Image;

/**
 * Uploaded checkout logo backend model: the core image backend
 * (png/jpg/jpeg/gif) with a VNPAY allow-list - PNG/JPG/JPEG/WEBP only.
 * SVG (and other vector formats) is rejected at upload time, mirroring
 * the Secomm_ZaloPay checkout logo (TASK-MCHN2T). Validation uses the
 * allow-list semantics of \Magento\MediaStorage\Model\File\Uploader.
 */
class Logo extends Image
{
    /**
     * Allow-list for the checkout logo upload. Replaces the core backend's
     * gif support with webp (PNG/JPG/JPEG/WEBP only).
     *
     * @return string[]
     */
    protected function _getAllowedExtensions(): array
    {
        return ['png', 'jpg', 'jpeg', 'webp'];
    }
}