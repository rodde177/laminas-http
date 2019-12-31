<?php

/**
 * @see       https://github.com/laminas/laminas-http for the canonical source repository
 * @copyright https://github.com/laminas/laminas-http/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-http/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Http\Client;

use Laminas\Http\Client\Adapter;
use Laminas\Uri\Uri;

/**
 * This Testsuite includes all Laminas_Http_Client that require a working web
 * server to perform. It was designed to be extendable, so that several
 * test suites could be run against several servers, with different client
 * adapters and configurations.
 *
 * Note that $this->baseuri must point to a directory on a web server
 * containing all the files under the files directory. You should symlink
 * or copy these files and set 'baseuri' properly.
 *
 * You can also set the proper constant in your test configuration file to
 * point to the right place.
 *
 * @group      Laminas_Http
 * @group      Laminas_Http_Client
 */
class SocketTest extends CommonHttpTests
{
    /**
     * Configuration array
     *
     * @var array
     */
    protected $config = array(
        'adapter' => 'Laminas\Http\Client\Adapter\Socket'
    );

    /**
     * Off-line common adapter tests
     */

    /**
     * Test that we can set a valid configuration array with some options
     * @group ZHC001
     */
    public function testConfigSetAsArray()
    {
        $config = array(
            'timeout'    => 500,
            'someoption' => 'hasvalue'
        );

        $this->_adapter->setOptions($config);

        $hasConfig = $this->_adapter->getConfig();
        foreach ($config as $k => $v) {
            $this->assertEquals($v, $hasConfig[$k]);
        }
    }

    public function testDefaultConfig()
    {
        $config = $this->_adapter->getConfig();
        $this->assertEquals(TRUE, $config['sslverifypeer']);
        $this->assertEquals(FALSE, $config['sslallowselfsigned']);
    }

    public function testConnectingViaSslEnforcesDefaultSslOptionsOnContext()
    {
        $config = array('timeout' => 30);
        $this->_adapter->setOptions($config);
        try {
            $this->_adapter->connect('localhost', 443, true);
        } catch (\Laminas\Http\Client\Adapter\Exception\RuntimeException $e) {
            // Test is designed to allow connect failure because we're interested
            // only in the stream context state created within that method.
        }
        $context = $this->_adapter->getStreamContext();
        $options = stream_context_get_options($context);
        $this->assertTrue($options['ssl']['verify_peer']);
        $this->assertFalse($options['ssl']['allow_self_signed']);
    }


    /**
     * Test Certificate File Option
     * The configuration is set to a legitimate certificate bundle file,
     * to exclude errors from being thrown from an invalid cafile context being set.
     */
    public function testConnectingViaSslUsesCertificateFileContext()
    {
        $config = array(
          'timeout' => 30,
          'sslcafile' => __DIR__ . '/_files/ca-bundle.crt',
        );
        $this->_adapter->setOptions($config);
        try {
            $this->_adapter->connect('localhost', 443, true);
        } catch (\Laminas\Http\Client\Adapter\Exception\RuntimeException $e) {
            // Test is designed to allow connect failure because we're interested
            // only in the stream context state created within that method.
        }
        $context = $this->_adapter->getStreamContext();
        $options = stream_context_get_options($context);
        $this->assertEquals($config['sslcafile'], $options['ssl']['cafile']);
    }

    /**
     * Test that a Laminas\Config object can be used to set configuration
     *
     * @link https://getlaminas.org/issues/browse/Laminas-5577
     */
    public function testConfigSetAsLaminasConfig()
    {
        $config = new \Laminas\Config\Config(array(
            'timeout'  => 400,
            'nested'   => array(
                'item' => 'value',
            )
        ));

        $this->_adapter->setOptions($config);

        $hasConfig = $this->_adapter->getConfig();
        $this->assertEquals($config->timeout, $hasConfig['timeout']);
        $this->assertEquals($config->nested->item, $hasConfig['nested']['item']);
    }

    /**
     * Check that an exception is thrown when trying to set invalid config
     *
     * @dataProvider invalidConfigProvider
     */
    public function testSetConfigInvalidConfig($config)
    {
        $this->setExpectedException(
            'Laminas\Http\Client\Adapter\Exception\InvalidArgumentException',
            'Array or Laminas\Config object expected');

        $this->_adapter->setOptions($config);
    }

    /**
     * Stream context related tests
     */

    public function testGetNewStreamContext()
    {
        $adapterClass = $this->config['adapter'];
        $adapter = new $adapterClass;
        $context = $adapter->getStreamContext();

        $this->assertEquals('stream-context', get_resource_type($context));
    }

    public function testSetNewStreamContextResource()
    {
        $adapterClass = $this->config['adapter'];
        $adapter = new $adapterClass;
        $context = stream_context_create();

        $adapter->setStreamContext($context);

        $this->assertEquals($context, $adapter->getStreamContext());
    }

    public function testSetNewStreamContextOptions()
    {
        $adapterClass = $this->config['adapter'];
        $adapter = new $adapterClass;
        $options = array(
            'socket' => array(
                'bindto' => '1.2.3.4:0'
            ),
            'ssl' => array(
                'capath'            => null,
                'verify_peer'       => true,
                'allow_self_signed' => false
            )
        );

        $adapter->setStreamContext($options);

        $this->assertEquals($options, stream_context_get_options($adapter->getStreamContext()));
    }

    /**
     * Test that setting invalid options / context causes an exception
     *
     * @dataProvider      invalidContextProvider
     */
    public function testSetInvalidContextOptions($invalid)
    {
        $this->setExpectedException(
            'Laminas\Http\Client\Adapter\Exception\InvalidArgumentException',
            'Expecting either a stream context resource or array');

        $adapterClass = $this->config['adapter'];
        $adapter = new $adapterClass;
        $adapter->setStreamContext($invalid);
    }

    public function testSetHttpsStreamContextParam()
    {
        if ($this->client->getUri()->getScheme() != 'https') {
            $this->markTestSkipped();
        }

        $adapterClass = $this->config['adapter'];
        $adapter = new $adapterClass;
        $adapter->setStreamContext(array(
            'ssl' => array(
                'capture_peer_cert' => true,
                'capture_peer_chain' => true
            )
        ));

        $this->client->setAdapter($adapter);
        $this->client->setUri($this->baseuri . '/testSimpleRequests.php');
        $this->client->request();

        $opts = stream_context_get_options($adapter->getStreamContext());
        $this->assertTrue(isset($opts['ssl']['peer_certificate']));
    }

    /**
     * Test that we get the right exception after a socket timeout
     *
     * @link https://getlaminas.org/issues/browse/Laminas-7309
     */
    public function testExceptionOnReadTimeout()
    {
        // Set 1 second timeout
        $this->client->setOptions(array('timeout' => 1));

        $start = microtime(true);

        try {
            $this->client->send();
            $this->fail('Expected a timeout Laminas\Http\Client\Adapter\Exception\TimeoutException');
        } catch (Adapter\Exception\TimeoutException $e) {
            $this->assertEquals(Adapter\Exception\TimeoutException::READ_TIMEOUT, $e->getCode());
        }

        $time = (microtime(true) - $start);

        // We should be very close to 1 second
        $this->assertLessThan(2, $time);
    }

    /**
     * Test that a chunked response with multibyte characters is properly read
     *
     * This can fail in various PHP environments - for example, when mbstring
     * overloads substr() and strlen(), and mbstring's internal encoding is
     * not a single-byte encoding.
     *
     * @link https://getlaminas.org/issues/browse/Laminas-6218
     */
    public function testMultibyteChunkedResponseLaminas6218()
    {
        $md5 = '7667818873302f9995be3798d503d8d3';

        $response = $this->client->send();
        $this->assertEquals($md5, md5($response->getBody()));
    }

    /**
     * Verifies that writing on a socket is considered valid even if 0 bytes
     * were written.
     */
    public function testAllowsZeroWrittenBytes()
    {
        $this->_adapter->connect('localhost');
        require_once __DIR__ . '/_files/fwrite.php';
        $this->_adapter->write('GET', new Uri('tcp://localhost:80/'), '1.1', array(), 'test body');
    }

    /**
     * Data Providers
     */

    /**
     * Provide invalid context resources / options
     *
     * @return array
     */
    public static function invalidContextProvider()
    {
        return array(
            array(new \stdClass()),
            array(fopen('data://text/plain,', 'r')),
            array(false),
            array(null)
        );
    }
}
