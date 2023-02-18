# 介绍
该组件基于hyperf框架，构建实现服务发现的grpc客户端，我们实现了以etcd为服务中心的组件支持，
目前仅支持对接go-micro。

#配置
添加配置文件 etcd.php
command: `php bin/hyperf.php vendor:publish chllen/hyperf-grpc-client`
```bash
return [
    'uri' => 'http://127.0.0.1:2379',
    'version' => 'v3beta',
    'retry_interval' => 5,
    'path_prefix' => '/micro/registry',
    'framework' => 'go-micro',
    'options' => [
        'timeout' => 10, 
    ],
];
```

#使用
封装类```Chllen\HyperfGrpcClient\GrpcClient```继承于```\Hyperf\GrpcClient\BaseClient``` ,根据 .proto 文件中的定义, 按需扩展:
```bash
use Chllen\HyperfGrpcClient\GrpcClient

class OrderClient extends GrpcClient
{
    public function create(Order $order)
    {
        return $this->_simpleRequest(
            '/orderService/create',
            $order,
            [OrderReply::class, 'decode']
        );
    }
}
```