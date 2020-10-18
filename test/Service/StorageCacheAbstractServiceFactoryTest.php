<?php

/**
 * @see       https://github.com/laminas/laminas-cache for the canonical source repository
 * @copyright https://github.com/laminas/laminas-cache/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-cache/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Cache\Service;

use Interop\Container\ContainerInterface;
use Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use Laminas\Cache\Storage\Adapter\AbstractAdapter;
use Laminas\Cache\Storage\Adapter\Memory;
use Laminas\Cache\Storage\AdapterPluginManager;
use Laminas\Cache\Storage\Plugin\PluginInterface;
use Laminas\Cache\Storage\PluginManager;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\StorageFactory;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers Laminas\Cache\StorageFactory<extended>
 */
class StorageCacheAbstractServiceFactoryTest extends TestCase
{
    use ProphecyTrait;

    protected $sm;

    public function setUp(): void
    {
        StorageFactory::resetAdapterPluginManager();
        StorageFactory::resetPluginManager();
        $config = [
            'services' => [
                'config' => [
                    'caches' => [
                        'Memory' => [
                            'adapter' => 'Memory',
                            'plugins' => ['Serializer', 'ClearExpiredByFactor'],
                        ],
                        'Foo' => [
                            'adapter' => 'Memory',
                            'plugins' => ['Serializer', 'ClearExpiredByFactor'],
                        ],
                    ]
                ],
            ],
            'abstract_factories' => [
                StorageCacheAbstractServiceFactory::class
            ]
        ];
        $this->sm = new ServiceManager();
        (new Config($config))->configureServiceManager($this->sm);
    }

    public function tearDown(): void
    {
        StorageFactory::resetAdapterPluginManager();
        StorageFactory::resetPluginManager();
    }

    public function testCanLookupCacheByName()
    {
        $this->assertTrue($this->sm->has('Memory'));
        $this->assertTrue($this->sm->has('Foo'));
    }

    public function testCanRetrieveCacheByName()
    {
        $cacheA = $this->sm->get('Memory');
        $this->assertInstanceOf(Memory::class, $cacheA);

        $cacheB = $this->sm->get('Foo');
        $this->assertInstanceOf(Memory::class, $cacheB);

        $this->assertNotSame($cacheA, $cacheB);
    }

    public function testInvalidCacheServiceNameWillBeIgnored()
    {
        $this->assertFalse($this->sm->has('invalid'));
    }

    public function testSetsFactoryAdapterPluginManagerInstanceOnInvocation()
    {
        $adapter = $this->prophesize(AbstractAdapter::class);
        $adapter->willImplement(StorageInterface::class);
        $adapter->setOptions(Argument::any())->shouldNotBeCalled();
        $adapter->hasPlugin(Argument::any(), Argument::any())->shouldNotBeCalled();
        $adapter->addPlugin(Argument::any(), Argument::any())->shouldNotBeCalled();

        $adapterPluginManager = $this->prophesize(AdapterPluginManager::class);
        $adapterPluginManager->get('Memory')->willReturn($adapter->reveal());

        $container = $this->prophesize(ContainerInterface::class);
        $container->has(AdapterPluginManager::class)->willReturn(true);
        $container->get(AdapterPluginManager::class)->willReturn($adapterPluginManager->reveal());
        $container->has(PluginManager::class)->willReturn(false);
        $container->has(\Zend\Cache\Storage\PluginManager::class)->willReturn(false);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'caches' => [ 'Cache' => [ 'adapter' => 'Memory' ]],
        ]);

        $factory = new StorageCacheAbstractServiceFactory();
        $this->assertSame($adapter->reveal(), $factory($container->reveal(), 'Cache'));
        $this->assertSame($adapterPluginManager->reveal(), StorageFactory::getAdapterPluginManager());
    }

    public function testSetsFactoryPluginManagerInstanceOnInvocation()
    {
        $plugin = $this->prophesize(PluginInterface::class);
        $plugin->setOptions(Argument::any())->shouldNotBeCalled();

        $pluginManager = $this->prophesize(PluginManager::class);
        $pluginManager->get('Serializer')->willReturn($plugin->reveal());

        $adapter = $this->prophesize(AbstractAdapter::class);
        $adapter->willImplement(StorageInterface::class);
        $adapter->setOptions(Argument::any())->shouldNotBeCalled();
        $adapter->hasPlugin($plugin->reveal(), Argument::any())->willReturn(false);
        $adapter->addPlugin($plugin->reveal(), Argument::any())->shouldBeCalled();

        $adapterPluginManager = $this->prophesize(AdapterPluginManager::class);
        $adapterPluginManager->get('Memory')->willReturn($adapter->reveal());

        $container = $this->prophesize(ContainerInterface::class);
        $container->has(AdapterPluginManager::class)->willReturn(true);
        $container->get(AdapterPluginManager::class)->willReturn($adapterPluginManager->reveal());
        $container->has(PluginManager::class)->willReturn(true);
        $container->get(PluginManager::class)->willReturn($pluginManager->reveal());

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'caches' => [ 'Cache' => [
                'adapter' => 'Memory',
                'plugins' => ['Serializer'],
            ]],
        ]);

        $factory = new StorageCacheAbstractServiceFactory();
        $factory($container->reveal(), 'Cache');
        $this->assertSame($pluginManager->reveal(), StorageFactory::getPluginManager());
    }
}
