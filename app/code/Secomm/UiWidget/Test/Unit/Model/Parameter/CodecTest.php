<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Parameter;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Parameter\Codec;

class CodecTest extends TestCase
{
    /** @var Codec */
    private Codec $codec;

    protected function setUp(): void
    {
        $this->codec = new Codec();
    }

    public function testRoundTripIsDeterministicAndPreservesCollectionOrder(): void
    {
        $first = ['title' => 'Xin chào', 'items' => [['id' => 2], ['id' => 1]]];
        $second = ['items' => [['id' => 2], ['id' => 1]], 'title' => 'Xin chào'];

        self::assertSame($this->codec->encode($first), $this->codec->encode($second));
        self::assertSame($second, $this->codec->decode($this->codec->encode($first)));
    }

    public function testRejectsUnsupportedVersionAndMalformedPayload(): void
    {
        self::assertNull($this->codec->decode($this->encodeEnvelope(['version' => 2, 'data' => []])));
        self::assertNull($this->codec->decode('not+a+base64url'));
    }

    public function testRejectsDepthItemAndByteLimits(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'too deep']]]]]]];

        self::assertSame('', $this->codec->encode($deep));
        self::assertSame('', $this->codec->encode(['items' => array_fill(0, Codec::MAX_ITEMS + 1, 'x')]));
        self::assertSame('', $this->codec->encode(['text' => str_repeat('x', Codec::MAX_ENCODED_BYTES)]));
    }

    public function testRejectsListAtPayloadRoot(): void
    {
        self::assertNull($this->codec->decode($this->encodeEnvelope(['version' => 1, 'data' => ['one']])));
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function encodeEnvelope(array $envelope): string
    {
        $json = json_encode($envelope, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
