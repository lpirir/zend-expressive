<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\RunInterface;
use Zend\Expressive\Middleware\WhoopsErrorResponseGenerator;

class WhoopsErrorResponseGeneratorTest extends TestCase
{
    public function setUp()
    {
        // Run is marked final in 2.X, but in that version, we can mock the
        // RunInterface. 1.X has only Run, and it is not final.
        $this->whoops = interface_exists(RunInterface::class)
            ? $this->prophesize(RunInterface::class)
            : $this->prophesize(Run::class);

        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->stream   = $this->prophesize(StreamInterface::class);
    }

    public function testWritesResultsOfWhoopsExceptionsHandlingToResponse()
    {
        $error = new RuntimeException();

        $this->whoops->getHandlers()->willReturn([]);
        $this->whoops->handleException($error)->willReturn('WHOOPS');

        // Could do more assertions here, but these will be sufficent for
        // ensuring that the method for injecting metadata is never called.
        $this->request->getAttribute('originalUri', false)->shouldNotBeCalled();
        $this->request->getAttribute('originalRequest', false)->shouldNotBeCalled();

        $this->response->getBody()->will([$this->stream, 'reveal']);

        $this->stream->write('WHOOPS')->shouldBeCalled();

        $generator = new WhoopsErrorResponseGenerator($this->whoops->reveal());

        $this->assertSame(
            $this->response->reveal(),
            $generator($error, $this->request->reveal(), $this->response->reveal())
        );
    }

    public function testAddsRequestMetadataToWhoopsPrettyPageHandler()
    {
        $error = new RuntimeException();

        $handler = $this->prophesize(PrettyPageHandler::class);
        $handler
            ->addDataTable('Expressive Application Request', [
                'HTTP Method'            => 'POST',
                'URI'                    => 'https://example.com/foo',
                'Script'                 => __FILE__,
                'Headers'                => [],
                'Cookies'                => [],
                'Attributes'             => [],
                'Query String Arguments' => [],
                'Body Params'            => [],
            ])
            ->shouldBeCalled();

        $this->whoops->getHandlers()->willReturn([$handler->reveal()]);
        $this->whoops->handleException($error)->willReturn('WHOOPS');

        $this->request->getAttribute('originalUri', false)->willReturn('https://example.com/foo');
        $this->request->getAttribute('originalRequest', false)->will([$this->request, 'reveal']);
        $this->request->getMethod()->willReturn('POST');
        $this->request->getServerParams()->willReturn(['SCRIPT_NAME' => __FILE__]);
        $this->request->getHeaders()->willReturn([]);
        $this->request->getCookieParams()->willReturn([]);
        $this->request->getAttributes()->willReturn([]);
        $this->request->getQueryParams()->willReturn([]);
        $this->request->getParsedBody()->willReturn([]);

        $this->response->getBody()->will([$this->stream, 'reveal']);

        $this->stream->write('WHOOPS')->shouldBeCalled();

        $generator = new WhoopsErrorResponseGenerator($this->whoops->reveal());

        $this->assertSame(
            $this->response->reveal(),
            $generator($error, $this->request->reveal(), $this->response->reveal())
        );
    }
}