<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\Seo\Plugin\Adminhtml;

use Closure;
use Exception;
use Magento\Config\Model\Config;

class ConfigPlugin
{
    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function aroundSave(
        Config $config,
        Closure $proceed
    ) {
        if ($config->getData('section') == 'seo') {
            $data = $config->getData('groups');

            if (isset($data['image'])) {
                $fieldsData = $data['image']['fields'];

                if (isset($fieldsData['image_url_template']['value'])) {
                    $this->validateField(
                        $fieldsData['image_url_template']['value'],
                        (string)__('Template for URL key of Product Images')
                    );
                }

                if (isset($fieldsData['image_alt_template']['value'])) {
                    $this->validateField(
                        $fieldsData['image_alt_template']['value'],
                        (string)__('Template for Product Images Alt')
                    );
                }

                if (isset($fieldsData['image_title_template']['value'])) {
                    $this->validateField(
                        $fieldsData['image_title_template']['value'],
                        (string)__('Template for Product Images Title')
                    );
                }
            }
        }

        return $proceed();
    }

    private function validateField(string $value, string $field)
    {
        preg_match_all('/\[\w*\]/', $value, $match);

        foreach ($match[0] as $m) {
            if (strpos($m, '[page_') !== false) {
                throw new Exception(
                    'Page variables are not allowed for the "' . $field . '" field.'
                );
            }
        }
    }
}
