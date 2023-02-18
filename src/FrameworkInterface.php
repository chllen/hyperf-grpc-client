<?php
namespace Chllen\HyperfGrpcClient;

interface FrameworkInterface
{
    public function parseValue(string $body) : ?array;

    public function parseKey(string $key) : ?array;
}