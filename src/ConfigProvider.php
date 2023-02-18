<?php

declare(strict_types=1);

namespace Chllen\HyperfGrpcClient;


use Chllen\HyperfServiceMicro\Listener\SearchServiceListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [

            ],
            'listeners' => [
                SearchServiceListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for etcd.',
                    'source' => __DIR__ . '/../publish/etcd.php',
                    'destination' => BASE_PATH . '/config/autoload/etcd.php',
                ],
            ],
        ];
    }
}
