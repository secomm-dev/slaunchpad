<?php
namespace Magezon\Core\Controller\Adminhtml\Batch;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterList;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\FrontControllerInterface;
use Magezon\Core\Helper\InputStream;

class Index extends \Magento\Backend\App\Action
{
    private $jsonFactory;
    private $request;
    private $routerList;
    private $actionFactory;
    private $response;
    private $frontController;
    private $count;
    private $inputStream;

    protected $_requiredParams = ['moduleFrontName', 'actionPath', 'actionName'];

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        RequestInterface $request,
        RouterList $routerList,
        ActionFactory $actionFactory,
        ResponseInterface $response,
        FrontControllerInterface $frontController,
        InputStream $inputStream
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->routerList = $routerList;
        $this->actionFactory = $actionFactory;
        $this->response = $response;
        $this->frontController = $frontController;
        $this->inputStream = $inputStream;
    }

     /**
     * Parse request URL params
     *
     * @param string $request
     * @return array
     */
    protected function parseRequest(string $path)
    {
        $output = [];

        $path = trim($path, '/');

        $params = explode('/', strlen($path) ? $path : $this->pathConfig->getDefaultPath());
        foreach ($this->_requiredParams as $paramName) {
            $output[$paramName] = array_shift($params);
        }

        for ($i = 0, $l = count($params); $i < $l; $i += 2) {
            $output['variables'][$params[$i]] = isset($params[$i + 1]) ? urldecode($params[$i + 1]) : '';
        }
        return $output;
    }

    public function execute()
    {
        $request = $this->getRequest();

        if ($request->isOptions()) {
            $this->getResponse()->representJson(
            $this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode([
                    'endpoints' => [
                        [
                            'args' => [
                                'requests' => [
                                    'maxItems' => 100
                                ]
                            ]
                        ]
                    ]
                ])
            );
            return;
        }

        $responses = [];

        foreach($request->getPostValue('requests') as $_request) {
            $method = strtoupper($_request['method']);
            $path = trim($_request['path'], '/');
            $params = $_request['body'] ?? [];

            $newRequest = clone $this->getRequest();
            $newRequest->setMethod($method);
            $newRequest->setPathInfo('/' . $path);
            $newRequest->setDispatched(false);

            $newRequest->setModuleName('file-manager');
            $newRequest->setControllerName('assets');
            $newRequest->setActionName('index');

            $newRequest->setPostValue($params);

            $newRequest->clearParams();
            $params = $this->parseRequest($path);
            $newRequest->setParams(isset($params['variables']) ? $params['variables'] : []);

            $result = $this->frontController->dispatch($newRequest);

            if ($result instanceof \Magento\Framework\Controller\Result\Json) {
                $responses[] = [
                    'body' => json_decode($result->getJson(), true),
                    'status' => 500
                ];
            } else if ($result && $result->getBody()) {
                $responses[] = [
                    'body' => json_decode($result->getBody(), true),
                    'status' => $result->getStatusCode(),
                    'headers' => $result->getHeaders()
                ];

            } else {
                
            }
        }
        
        $this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode([ 'responses' => $responses])
        );
        return;
    }
}