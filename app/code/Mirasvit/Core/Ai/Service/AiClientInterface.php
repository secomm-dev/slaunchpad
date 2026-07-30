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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Ai\Service;

interface AiClientInterface
{
    public const HTTP_METHOD_POST   = 'POST';

    public const CONTENT_TYPE_JSON      = 'application/json';
    public const CONTENT_TYPE_FORM_DATA = 'multipart/form-data';

    public const HEADER_AUTHORIZATION = 'Authorization';
    public const HEADER_CONTENT_TYPE  = 'Content-Type';
    public const HEADER_USER_AGENT    = 'User-Agent';

    public const DEFAULT_USER_AGENT = 'Mirasvit-AI-Client/1.0';

    public function sendRequest(string $endpoint, string $method, array $data = [], array $headers = []): array;

    public function setApiKey(string $apiKey): void;

    public function setBaseUrl(string $baseUrl): void;

    public function setTimeout(int $timeout): void;


    public function isConfigured(): bool;
}