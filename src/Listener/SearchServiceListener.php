<?php
declare(strict_types=1);

namespace Chllen\HyperfGrpcClient\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Psr\Container\ContainerInterface;
use Chllen\HyperfGrpcClient\Etcd\ServiceClient;

class SearchServiceListener implements ListenerInterface
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigInterface
     */
    protected $config;


    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class
        ];
    }

    /**
     * @param BeforeWorkerStart $event
     */
    public function process(object $event)
    {
        make(ServiceClient::class)->registerNodes()->watchNode();
    }

}
