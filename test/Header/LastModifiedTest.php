<?php

/**
 * @see       https://github.com/laminas/laminas-http for the canonical source repository
 * @copyright https://github.com/laminas/laminas-http/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-http/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Http\Header;

use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Header\LastModified;
use PHPUnit\Framework\TestCase;

class LastModifiedTest extends TestCase
{
    public function testExpiresFromStringCreatesValidLastModifiedHeader()
    {
        $lastModifiedHeader = LastModified::fromString('Last-Modified: Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertInstanceOf(HeaderInterface::class, $lastModifiedHeader);
        $this->assertInstanceOf(LastModified::class, $lastModifiedHeader);
    }

    public function testLastModifiedGetFieldNameReturnsHeaderName()
    {
        $lastModifiedHeader = new LastModified();
        $this->assertEquals('Last-Modified', $lastModifiedHeader->getFieldName());
    }

    public function testLastModifiedGetFieldValueReturnsProperValue()
    {
        $lastModifiedHeader = new LastModified();
        $lastModifiedHeader->setDate('Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertEquals('Sun, 06 Nov 1994 08:49:37 GMT', $lastModifiedHeader->getFieldValue());
    }

    public function testLastModifiedToStringReturnsHeaderFormattedString()
    {
        $lastModifiedHeader = new LastModified();
        $lastModifiedHeader->setDate('Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertEquals('Last-Modified: Sun, 06 Nov 1994 08:49:37 GMT', $lastModifiedHeader->toString());
    }

    /**
     * Implementation specific tests are covered by DateTest
     * @see LaminasTest\Http\Header\DateTest
     */

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackViaFromString()
    {
        $this->expectException(InvalidArgumentException::class);
        LastModified::fromString("Last-Modified: Sun, 06 Nov 1994 08:49:37 GMT\r\n\r\nevilContent");
    }
}
