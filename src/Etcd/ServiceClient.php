<?php


namespace Chllen\HyperfGrpcClient\Etcd;

use Chllen\HyperfGrpcClient\FrameworkManager;
use Chllen\HyperfGrpcClient\NodeManager;
use Chllen\HyperfServiceMicroEtcd\Etcd\WatcherInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\LoadBalancer\LoadBalancerManager;
use Hyperf\LoadBalancer\Node;
use Hyperf\ServiceGovernance\DriverManager;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use Hyperf\Contract\StdoutLoggerInterface;
use Etcdserverpb\WatchCreateRequest\FilterType;

class ServiceClient
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var StdoutLoggerInterface|mixed
     */
    protected $config;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $this->container->get(StdoutLoggerInterface::class);
        $this->config = $this->container->get(ConfigInterface::class);
    }


    public function registerNodes()
    {
        $consumers = $this->getConsumerConfig();

        if (!$consumers) {
            throw new InvalidArgumentException('Config of registry or nodes missing.');
        }

        foreach ($consumers as $consumer) {
            $this->createNode($consumer);
        }

        return $this;
    }


    protected function createNode($consumer)
    {
        $registryProtocol = $consumer['registry']['protocol'] ?? null;
        $registryAddress = $consumer['registry']['address'] ?? null;
        $serviceName = $consumer['service_name'] ?? '';
        $path_prefix = $consumer['path_prefix'] ?? "";
        $load_balancer = $consumer['load_balancer'] ?? "random";
        $protocol = $consumer['protocol'] ?? "grpc";
        // Current $consumer is the config of the specified consumer.
        if (!empty($registryProtocol) && $this->container->has(DriverManager::class)) {
            $governance = $this->container->get(DriverManager::class)->get($registryProtocol);
            if (!$governance) {
                throw new InvalidArgumentException(sprintf('Invalid protocol of registry %s', $registryProtocol));
            }
            $nodes = $governance->getNodes($registryAddress,$serviceName, [
                'protocol' => $protocol,
                'path_prefix' => $path_prefix,
            ]);
            foreach ($nodes as $id => $nodeArray) {
                foreach ($nodeArray as $node) {
                    $this->container->get(NodeManager::class)->register(
                        $serviceName,
                        $id,
                        new Node($node['host'], $node['port'], $node['weight'] ?? 0, $node['path_prefix'] ?? '')
                    );
                }
            }
        }

        // Not exists the registry config, then looking for the 'nodes' property.
        if (isset($consumer['nodes'])) {
            foreach ($consumer['nodes'] ?? [] as $item) {
                if (isset($item['host'], $item['port'])) {
                    if (!is_int($item['port'])) {
                        throw new InvalidArgumentException(sprintf('Invalid node config [%s], the port option has to a integer.', implode(':', $item)));
                    }
                    $this->container->get(NodeManager::class)->register(
                        $serviceName,
                        uniqid(),
                        new Node($node['host'], $node['port'], $node['weight'] ?? 0, $node['path_prefix'] ?? '')
                    );
                }
            }
        }
    }

    public function selectNode(string $serviceName) :Node
    {
        $consumer = $this->getConsumerByServiceName($serviceName);
        $load_balancer = $consumer['load_balancer'] ?? 'random';
        $nodes = $this->container->get(NodeManager::class)->get($serviceName);
        if (!$nodes) {
            $this->createNode($consumer);
        }
        $LoadBalancerManager = $this->container->get(LoadBalancerManager::class);

        return $LoadBalancerManager
            ->getInstance($serviceName, $load_balancer)
            ->setNodes($nodes)->select();
    }


    public function watchNode()
    {
        \Hyperf\Utils\Coroutine::create(function () {
            $interval = $this->getInterval();
            retry(INF, function () use ($interval) {
                try {
                    $watchClient = $this->watcherClient();
                    $watchCall = $watchClient->watchPrefix($this->getPathPrefix());
                    /**@var $reply \Etcdserverpb\WatchResponse */
                    while (true) {
                        [$reply, $status] = $watchCall->recv();

                        if ($status === 0) { // success
                            if ($reply->getCreated() || $reply->getCanceled()) {
                                continue;
                            }

                            foreach ($reply->getEvents() as $event) {
                                /**@var $event \Mvccpb\Event */
                                $type = $event->getType();
                                $kv = $event->getKv();

                                if (FilterType::NOPUT === $type) {
                                    //注册节点
                                    $framework = $this->container->get(FrameworkManager::class)->get($this->getFramework());
                                    if(!$framework){
                                        throw new InvalidArgumentException(sprintf('Invalid framework of registry %s', $this->getFramework()));
                                    }
                                    $value = $framework->parseValue($kv->getValue());
                                    if(empty($value['nodes'])) {
                                        throw new InvalidArgumentException('not found for registry nodes');
                                    }
                                    foreach ($value['nodes'] as $nodeArray) {
                                        foreach ($nodeArray as $node) {
                                            $this->container->get(NodeManager::class)->register(
                                                $value['name'],
                                                $value['id'],
                                                new Node($node['host'], $node['port'], $node['weight'] ?? 0, $node['path_prefix'] ?? '')
                                            );
                                        }
                                    }
                                    break;
                                } elseif (FilterType::NODELETE === $type) {
                                    $framework = $this->container->get(FrameworkManager::class)->get($this->getFramework());
                                    if(!$framework){
                                        throw new InvalidArgumentException(sprintf('Invalid framework of registry %s', $this->getFramework()));
                                    }
                                    $key = $framework->parseKey($kv->getKey());
                                    if (empty($key['name']) || empty($key['id'])) {
                                        throw new InvalidArgumentException('not found for service name or id');
                                    }
                                    $this->container->get(NodeManager::class)->deregister($key['name'], $key['id']);
                                    break;
                                }
                            }
                        } else {
                            throw new \RuntimeException('Connection failed');
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->logger->error((string)$exception);
                    throw $exception;
                }
            }, $interval * 1000);
        });
    }


    protected function watcherClient(): WatcherInterface
    {
        return $this->container->get(WatcherInterface::class);
    }


    protected function getConsumerConfig(): array
    {
        $config = $this->container->get(ConfigInterface::class);

        return $config->get('services.go_micro_consumers', []);
    }


    protected function getConsumerByServiceName($serviceName): array
    {
        $consumers = $this->config->get('services.go_micro_consumers', []);

        foreach ($consumers as $consumer) {
            if (isset($consumer['service_name']) && $consumer['service_name'] == $serviceName) {
                return $consumer;
            }
        }

        return $consumers;
    }

    protected function getEtcdConfig() : array
    {
        return $this->config->get('etcd', []);
    }

    protected function getInterval(): int
    {
        return (int)$this->getEtcdConfig()['retry_interval'] ?? 5;
    }

    protected function getPathPrefix(): string
    {
        return (string)$this->getEtcdConfig()['path_prefix'] ?? '';
    }

    protected function getFramework(): string
    {
        return (string)$this->getEtcdConfig()['framework'] ?? '';
    }
}