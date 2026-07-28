<?php
declare(strict_types=1);

namespace Secomm\DisableFileUpload\Plugin\Catalog;

use Magento\Catalog\Model\Product\Option\Type\File\ValidatorFile;
use Magento\Framework\Exception\LocalizedException;

class DisableCustomOptionFileUpload
{
    /**
     * Prevent file upload for product custom options
     *
     * @param ValidatorFile $subject
     * @param callable $proceed
     * @param mixed $processingParams
     * @param mixed $option
     * @return mixed
     * @throws LocalizedException
     */
    public function aroundValidate(ValidatorFile $subject, callable $proceed, $processingParams, $option)
    {
        throw new LocalizedException(__('File uploads for product custom options are disabled.'));
    }
}
