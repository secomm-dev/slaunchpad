<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Test\Unit\Service\Response;

use PHPUnit\Framework\TestCase;
use Secomm\AiCommerce\Service\InvalidParameterException;
use Secomm\AiCommerce\Service\InvalidStoreException;
use Secomm\AiCommerce\Service\NotFoundException;
use Secomm\AiCommerce\Service\Response\ErrorEnvelope;
use Secomm\AiCommerce\Service\SearchUnavailableException;

class ErrorEnvelopeTest extends TestCase
{
    /**
     * @var ErrorEnvelope
     */
    private $envelope;

    protected function setUp(): void
    {
        $this->envelope = new ErrorEnvelope();
    }

    public function testInvalidParameterMapsTo400(): void
    {
        $error = $this->envelope->build(new InvalidParameterException(__('bad')));

        $this->assertSame(400, $error['status']);
        $this->assertSame('invalid_parameter', $error['body']['error']['code']);
    }

    public function testInvalidStoreMapsTo400InvalidStore(): void
    {
        $error = $this->envelope->build(new InvalidStoreException(__('bad store')));

        $this->assertSame(400, $error['status']);
        $this->assertSame('invalid_store', $error['body']['error']['code']);
    }

    public function testNotFoundMapsTo404(): void
    {
        $error = $this->envelope->build(new NotFoundException(__('gone')));

        $this->assertSame(404, $error['status']);
        $this->assertSame('not_found', $error['body']['error']['code']);
    }

    public function testSearchUnavailableMapsTo503(): void
    {
        $error = $this->envelope->build(new SearchUnavailableException(__('down')));

        $this->assertSame(503, $error['status']);
        $this->assertSame('search_unavailable', $error['body']['error']['code']);
    }

    public function testMethodNotAllowed(): void
    {
        $error = $this->envelope->methodNotAllowed();

        $this->assertSame(405, $error['status']);
        $this->assertSame('method_not_allowed', $error['body']['error']['code']);
    }

    public function testInternalErrorNeverLeaksExceptionDetails(): void
    {
        $error = $this->envelope->internalError();

        $this->assertSame(500, $error['status']);
        $encoded = json_encode($error['body']);
        $this->assertStringNotContainsString('Exception', $encoded);
        $this->assertStringNotContainsString('.php', $encoded);
    }
}
