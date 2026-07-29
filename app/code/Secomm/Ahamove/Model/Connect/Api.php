<?php
/*
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Connect;

use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Secomm\Ahamove\Api\ConnectInterface;
use Secomm\Ahamove\Api\TracerInterface;
use Secomm\Ahamove\Helper\CurlBuilder;
use Secomm\Ahamove\Helper\Data as Config;
use Secomm\Ahamove\Model\Config\Source\ApiRequest\Status;
use Secomm\Ahamove\Model\Tracer;

/**
 * @method getCourierCode()
 */
class Api extends CurlBuilder implements
    ConnectInterface,
    TracerInterface
{
    const URL_TYPE_TOKEN = 'oauth2_token';

    protected string $actionName = '';

    protected string $endPoint = '';

    protected array $urls = [];

    protected string $courierCode = '';

    /**
     * @var Tracer
     */
    protected Tracer $tracer;

    /**
     * @var Config
     */
    protected Config $config;

    /**
     * To check whether the reload token has been executed or not
     *
     * @var bool
     */
    protected bool $flat = false;

    /**
     * Connect constructor.
     * @param Tracer $tracer
     * @param Config $config
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param WriterInterface $configWriter
     * @param EventManagerInterface $_eventManager
     */
    public function __construct(
        Tracer                          $tracer,
        Config                          $config,
        protected CacheInterface        $cache,
        protected SerializerInterface   $serializer,
        protected WriterInterface       $configWriter,
        protected EventManagerInterface $_eventManager
    )
    {
        $this->tracer = $tracer;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     * @return mixed
     * @throws Exception
     */
    public function post($isResend = false, $token = null): mixed
    {
        $this->packageOptions['data']['token'] = !is_null($token) ? $token : $this->config->getToken();
        $response = parent::post($isResend);
        $result = $this->processResponse($response);
        if (($result === Status::STATUS_CODE_TOKEN_NOT_FOUND || $result === Status::STATUS_CODE_AUTHENTICATION_FAIL) && !$this->flat) {
            //refresh token and re-run only one time
            $this->flat = true;
            $responseToken = $this->refreshToken();
            $responseToken = $this->processResponse($responseToken);
            $token = $responseToken['token'];
            $response = $this->post($isResend, $token);
        }
        return $response;
    }

    /**
     * @inheritDoc
     * @return mixed
     * @throws Exception
     */
    public function get($isResend = false): mixed
    {
        return parent::get($isResend);
    }

    /**
     * @param string $url
     * @return CurlBuilder
     * @throws Exception
     */
    public function toResource($url)
    {
        try {
            $url = $this->buildResourceUrl($url);
        } catch (Exception $exception) {
            throw new Exception('Invalid URL');
        }

        return parent::to($url);
    }

    /**
     * @param $action
     * @return string
     * @throws NoSuchEntityException
     */
    protected function buildResourceUrl($action): string
    {
        return $this->config->getEndpointApiShip24() . $action;
    }

    /**
     * @param string $url
     * @return CurlBuilder
     * @throws Exception
     */
    public function to($url)
    {
        try {
            $url = $this->buildUrl($url);
        } catch (Exception $exception) {
            throw new Exception('Invalid URL');
        }

        return parent::to($url);
    }

    /**
     * @param $action
     * @return string
     * @throws NoSuchEntityException
     * @throws Exception
     */
    protected function buildUrl($action): string
    {
        $endPoint = $this->config->getUrlAhamove();

        $this->setEndPoint($endPoint);
        if ($this->getEndPoint() === '') {
            throw new Exception('Missing endpoint url');
        }

        return $this->getEndPoint() . $action;
    }

    /**
     * @return string
     */
    public function getEndPoint(): string
    {
        return $this->endPoint;
    }

    /**
     * @param $endPoint
     * @return void
     */
    public function setEndPoint($endPoint): void
    {
        $this->endPoint = $endPoint;
    }

    /**
     * @param $isResend
     * @return mixed
     */
    public function put($isResend = false): mixed
    {
        return parent::put($isResend);
    }

    /**
     * @param $data
     * @return array|mixed
     */
    public function getDataSent($data = []): mixed
    {
        $data['curlOptions'] = $this->curlOptions;
        $data['packageOptions'] = $this->packageOptions;
        $data['actionName'] = $this->actionName;
        return $data;
    }

    /**
     * @param string $name
     * @return Api
     */
    public function name(string $name): static
    {
        $this->actionName = $name;
        $this->curlOptions['HTTPHEADER'] = [];
        $this->curlOptions['URL'] = '';
        $this->packageOptions['data'] = [];
        $this->flat = false;

        return $this;
    }

    /**
     * Remove cache
     */
    public function __destruct()
    {
        $this->cache->remove($this->actionName);
    }

    /**
     * @param mixed $response
     * @return mixed|void
     * @throws Exception
     */
    public function processResponse(mixed $response)
    {
        try {
            if (gettype($response) === 'object') {
                if ($response->status === Status::STATUS_CODE_SYSTEM_PROCESS_ERROR) {
                    throw new Exception($response->content['description']);
                } else {
                    if ($response->status === Status::STATUS_CODE_SUCCESS) {
                        return $response->content;
                    } else {
                        return $response->status;
                    }
                }
            } else {
                if ($response['status'] === Status::STATUS_CODE_SYSTEM_PROCESS_ERROR) {
                    if ($response->status === Status::STATUS_CODE_SUCCESS) {
                        return $response['content'];
                    } else {
                        return $response['status'];
                    }
                }else {
                    if ($response['status'] === Status::STATUS_CODE_SUCCESS) {
                        return $response['content'];
                    } else {
                        return $response['status'];
                    }
                }
            }
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * @return mixed
     * @throws Exception
     * @throws AlreadyExistsException
     */
    protected function reSend(): mixed
    {
        if (!$this->actionName) {
            throw new Exception('Name field required !');
        }

        $this->addAuthBasic();
        $this->addDefaultHeader();
        $this->withResponseHeaders();
        $this->returnResponseObject();

        // Fire event before sent
        $tracer = $this->tracer->beforeSend($this);
        // Backoff strategy parameters
        $maxRetries = 5; // Maximum number of retries
        $baseDelay = 1; // Initial delay in seconds
        $retryAttempts = 0;
        do {
            $result = $this->loadFromCache();
            if ($result !== false) {
                break;
            }
            $result = parent::send();
            if ($result->status === Status::STATUS_CODE_AUTHENTICATION_FAIL) {
                $retryAttempts++;

                if ($retryAttempts <= $maxRetries) {
                    $delay = $baseDelay * (2 ** ($retryAttempts - 1)); // Exponential backoff
                    sleep($delay); // Wait for the calculated delay before retrying
                }
            } else {
                //save the response to cache
                $this->cacheResponse($result);
                break; // API call succeeded, exit the loop
            }
        } while ($retryAttempts <= $maxRetries);

        // Fire event after sent
        $this->tracer->afterSend($this, $result);

        return $result;
    }

    /**
     * @return $this
     */
    protected function addAuthBasic(): static
    {
        $this->withCurlOption('HTTPAUTH', CURLAUTH_BASIC);
        return $this;
    }

    /**
     * @return $this
     */
    protected function addDefaultHeader(): static
    {
        $this->withHeaders([
            'User-Agent' => 'Magento'
        ]);
        return $this;
    }

    /**
     * @return object|bool
     */
    public function loadFromCache(): bool|object
    {
        if (!$this->cache->load($this->actionName)) {
            return false;
        }
        return (object)$this->serializer->unserialize($this->cache->load($this->actionName));
    }

    /**
     * @return mixed
     * @throws Exception
     * @throws AlreadyExistsException
     */
    protected function send(): mixed
    {
        if (!$this->actionName) {
            throw new Exception('Name field required !');
        }

        $this->addAuthBasic();
        $this->addDefaultHeader();
        $this->withResponseHeaders();
        $this->returnResponseObject();

        // Fire event before sent
        $tracer = $this->tracer->beforeSend($this);

        $result = parent::send();

        // Fire event after sent
        $this->tracer->afterSend($this, $result);

        return $result;
    }

    /**
     * Save the response to cache
     *
     * @param mixed $result
     * @return void
     */
    public function cacheResponse(mixed $result): void
    {
        $this->cache->save(
            $this->serializer->serialize($result),
            $this->actionName,
            [\Magento\Framework\App\Config::CACHE_TAG],
            8640
        );
    }

    /**
     * @param $bearToken
     * @return $this
     */
    protected function addBearerToken($bearToken)
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $bearToken]);
        return $this;
    }

    /**
     * This function is called when the token has expired
     */
    public function refreshToken()
    {
        $data = new \Magento\Framework\DataObject(['response' => null]);
        $this->_eventManager->dispatch('refresh_ahamove_token', ['data' => $data]);
        $response = $data->getResponse();
        return $response;
    }
}
