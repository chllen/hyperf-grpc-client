<?php


namespace Chllen\HyperfGrpcClient;

class FrameworkManager
{
    /**
     * @var FrameworkInterface[]
     */
    protected $drivers = [];

    public function register(string $name, FrameworkInterface $frame)
    {
        $this->drivers[$name] = $frame;
    }

    public function get(string $name): ?FrameworkInterface
    {
        return $this->drivers[$name] ?? null;
    }
}