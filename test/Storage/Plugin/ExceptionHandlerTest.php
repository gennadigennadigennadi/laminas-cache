<?php

/**
 * @see       https://github.com/laminas/laminas-cache for the canonical source repository
 * @copyright https://github.com/laminas/laminas-cache/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-cache/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Cache\Storage\Plugin;

use ArrayObject;
use Laminas\Cache;
use Laminas\Cache\Storage\ExceptionEvent;
use Laminas\EventManager\Test\EventListenerIntrospectionTrait;
use LaminasTest\Cache\Storage\TestAsset\MockAdapter;

/**
 * @covers Laminas\Cache\Storage\Plugin\ExceptionHandler<extended>
 */
class ExceptionHandlerTest extends CommonPluginTest
{
    use EventListenerIntrospectionTrait;

    // @codingStandardsIgnoreStart
    /**
     * The storage adapter
     *
     * @var \Laminas\Cache\Storage\Adapter\AbstractAdapter
     */
    protected $_adapter;
    // @codingStandardsIgnoreEnd

    public function setUp(): void
    {
        $this->_adapter = new MockAdapter();
        $this->_options = new Cache\Storage\Plugin\PluginOptions();
        $this->_plugin  = new Cache\Storage\Plugin\ExceptionHandler();
        $this->_plugin->setOptions($this->_options);

        parent::setUp();
    }

    public function getCommonPluginNamesProvider()
    {
        return [
            ['exception_handler'],
            ['exceptionhandler'],
            ['ExceptionHandler'],
            ['exceptionHandler'],
        ];
    }

    public function testAddPlugin()
    {
        $this->_adapter->addPlugin($this->_plugin);

        // check attached callbacks
        $expectedListeners = [
            'getItem.exception'  => 'onException',
            'getItems.exception' => 'onException',

            'hasItem.exception'  => 'onException',
            'hasItems.exception' => 'onException',

            'getMetadata.exception'  => 'onException',
            'getMetadatas.exception' => 'onException',

            'setItem.exception'  => 'onException',
            'setItems.exception' => 'onException',

            'addItem.exception'  => 'onException',
            'addItems.exception' => 'onException',

            'replaceItem.exception'  => 'onException',
            'replaceItems.exception' => 'onException',

            'touchItem.exception'  => 'onException',
            'touchItems.exception' => 'onException',

            'removeItem.exception'  => 'onException',
            'removeItems.exception' => 'onException',

            'checkAndSetItem.exception' => 'onException',

            'incrementItem.exception'  => 'onException',
            'incrementItems.exception' => 'onException',

            'decrementItem.exception'  => 'onException',
            'decrementItems.exception' => 'onException',

            'clearExpired.exception' => 'onException',
        ];
        foreach ($expectedListeners as $eventName => $expectedCallbackMethod) {
            $listeners = $this->getArrayOfListenersForEvent($eventName, $this->_adapter->getEventManager());

            // event should attached only once
            $this->assertSame(1, count($listeners));

            // check expected callback method
            $cb = array_shift($listeners);
            $this->assertArrayHasKey(0, $cb);
            $this->assertSame($this->_plugin, $cb[0]);
            $this->assertArrayHasKey(1, $cb);
            $this->assertSame($expectedCallbackMethod, $cb[1]);
        }
    }

    public function testRemovePlugin()
    {
        $this->_adapter->addPlugin($this->_plugin);
        $this->_adapter->removePlugin($this->_plugin);

        // no events should be attached
        $this->assertEquals(0, count($this->getEventsFromEventManager($this->_adapter->getEventManager())));
    }

    public function testOnExceptionCallCallback()
    {
        $expectedException = new \Exception();
        $callbackCalled    = false;

        $this->_options->setExceptionCallback(function ($exception) use ($expectedException, &$callbackCalled) {
            $callbackCalled = ($exception === $expectedException);
        });

        // run onException
        $result = null;
        $event = new ExceptionEvent('getItem.exception', $this->_adapter, new ArrayObject([
            'key'     => 'key',
            'options' => []
        ]), $result, $expectedException);
        $this->_plugin->onException($event);

        $this->assertTrue(
            $callbackCalled,
            "Expected callback wasn't called or the expected exception wasn't the first argument"
        );
    }

    public function testDontThrowException()
    {
        $this->_options->setThrowExceptions(false);

        // run onException
        $result = 'test';
        $event = new ExceptionEvent('getItem.exception', $this->_adapter, new ArrayObject([
            'key'     => 'key',
            'options' => []
        ]), $result, new \Exception());
        $this->_plugin->onException($event);

        $this->assertFalse($event->getThrowException());
        $this->assertSame('test', $event->getResult());
    }
}
