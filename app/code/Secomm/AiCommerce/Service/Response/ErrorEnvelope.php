<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Response;

use Secomm\AiCommerce\Service\FacadeException;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\InvalidStoreException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\SearchUnavailableException;

/**
 * Deterministic error envelope {error:{code,message}}.
 *
 * Never includes file paths, class names, SQL, engine details or stack
 * traces — the message is a fixed public string chosen by code.
 */
class ErrorEnvelope
{
    private const MESSAGE_INVALID_PARAMETER = 'Invalid request parameters.';
    private const MESSAGE_INVALID_STORE = 'Invalid store code.';
    private const MESSAGE_NOT_FOUND = 'Resource not found.';
    private const MESSAGE_METHOD_NOT_ALLOWED = 'Method not allowed.';
    private const MESSAGE_SEARCH_UNAVAILABLE = 'Search is temporarily unavailable.';
    private const MESSAGE_INTERNAL = 'Internal error.';

    /**
     * Map a facade exception to its envelope + HTTP status.
     *
     * @param FacadeException $exception caught facade exception
     * @return array{status: int, body: array{error: array{code: string, message: string}}}
     */
    public function build(FacadeException $exception): array
    {
        if ($exception instanceof SearchUnavailableException) {
            return $this->envelope(503, 'search_unavailable', self::MESSAGE_SEARCH_UNAVAILABLE);
        }

        if ($exception instanceof NotFoundException) {
            return $this->envelope(404, 'not_found', self::MESSAGE_NOT_FOUND);
        }

        if ($exception instanceof InvalidStoreException) {
            return $this->envelope(400, 'invalid_store', self::MESSAGE_INVALID_STORE);
        }

        if ($exception instanceof InvalidParameterException) {
            return $this->envelope(400, 'invalid_parameter', self::MESSAGE_INVALID_PARAMETER);
        }

        return $this->envelope(500, 'internal_error', self::MESSAGE_INTERNAL);
    }

    /**
     * Method-not-allowed envelope (405).
     *
     * @return array{status: int, body: array{error: array{code: string, message: string}}}
     */
    public function methodNotAllowed(): array
    {
        return $this->envelope(405, 'method_not_allowed', self::MESSAGE_METHOD_NOT_ALLOWED);
    }

    /**
     * Internal-error envelope (500).
     *
     * @return array{status: int, body: array{error: array{code: string, message: string}}}
     */
    public function internalError(): array
    {
        return $this->envelope(500, 'internal_error', self::MESSAGE_INTERNAL);
    }

    /**
     * Assemble the envelope structure.
     *
     * @param int $status HTTP status
     * @param string $code machine-readable error code
     * @param string $message fixed public message
     * @return array{status: int, body: array{error: array{code: string, message: string}}}
     */
    private function envelope(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['error' => ['code' => $code, 'message' => $message]]];
    }
}
