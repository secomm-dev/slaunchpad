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

namespace Mirasvit\SeoAi\Plugin\Framework\App\FrontController;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Mirasvit\SeoAi\Service\ContextService;
use Mirasvit\SeoAi\Service\PromptService;

class RetrieveContextFromUrlPlugin
{
    private $jsonFactory;

    private $contextService;

    public function __construct(
        ContextService $contextService,
        JsonFactory    $jsonFactory
    ) {
        $this->contextService = $contextService;
        $this->jsonFactory    = $jsonFactory;
    }

    public function afterDispatch($subject, $result, RequestInterface $request)
    {
        if ($request->getParam(PromptService::QUERY_PARAM)) {
            $params = $request->getParams();

            $type     = $request->getFullActionName();
            $entityId = $request->getParam('id') ?: $request->getParam($request->getControllerName() . '_id');

            $idKey = '';

            if (!$entityId) {
                foreach ($params as $key => $value) {
                    if (is_string($value) && (strpos($key, '_id') === strlen($value) - 3)) {
                        $idKey    = $key;
                        $entityId = $value;
                        break;
                    }
                }
            }

            unset($params['id']);
            unset($params[$request->getControllerName() . '_id']);
            unset($params[$idKey]);
            unset($params[PromptService::QUERY_PARAM]);

            return $this->jsonFactory->create()->setData(
                $this->contextService->getContextByType($type, (int)$entityId, $params)
            );
        }

        return $result;
    }
}
