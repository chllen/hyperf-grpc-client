<?php


namespace Chllen\HyperfGrpcClient;


use Chllen\HyperfServiceMicro\Etcd\ServiceClient;
use Hyperf\GrpcClient\BaseClient;
use Hyperf\Utils\ApplicationContext;

class GrpcClient extends BaseClient
{
    public function __construct(string $serviceName, array $options = [])
    {
        $node = ApplicationContext::getContainer()->get(ServiceClient::class)->selectNode($serviceName);
        $hostname = $node ? join(':',[$node['host'],$node['port']]) : "";
        parent::__construct($hostname, $options);
    }
}