<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Route\ConfigInterface;
use Magento\Framework\App\Router\ActionList;
use Magento\Framework\App\RouterInterface;

/**
 * Matches root-path /llms.txt requests before the standard router,
 * mirroring the Magento_Robots controller router pattern (SPEC-TASK-0X552E §4.1).
 */
class Router implements RouterInterface
{
    private const LLMS_TXT = 'llms.txt';

    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * @var ActionList
     */
    private $actionList;

    /**
     * @var ConfigInterface
     */
    private $routeConfig;

    /**
     * @param ActionFactory $actionFactory action factory
     * @param ActionList $actionList router action list
     * @param ConfigInterface $routeConfig route config
     */
    public function __construct(
        ActionFactory $actionFactory,
        ActionList $actionList,
        ConfigInterface $routeConfig
    ) {
        $this->actionFactory = $actionFactory;
        $this->actionList = $actionList;
        $this->routeConfig = $routeConfig;
    }

    /**
     * Match a root-path /llms.txt request to the llms index action.
     *
     * @param RequestInterface $request incoming request
     * @return ActionInterface|null matched action or null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if (trim($request->getPathInfo(), '/') !== self::LLMS_TXT) {
            return null;
        }

        $modules = $this->routeConfig->getModulesByFrontName('llms');
        if (empty($modules)) {
            return null;
        }

        $actionClassName = $this->actionList->get($modules[0], null, 'index', 'index');

        return $this->actionFactory->create($actionClassName);
    }
}
