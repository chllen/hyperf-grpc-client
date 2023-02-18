<?php


namespace Chllen\HyperfGrpcClient;

use Hyperf\LoadBalancer\Node;

class NodeManager
{
    /**
     * @var Node[]
     */
    protected $node = [];

    /**
     * all node of a service
     * @param string $name
     * @return array|null
     */
    public function get(string $name): ?array
    {
        if(isset($this->node[$name])){
            return array_reduce($this->node[$name], function ($result, $value) {
                return array_merge($result, array_values($value));
            }, array());
        }
        return null;
    }

    /**
     * register a node to the manager
     * @param string $name
     * @param string $id
     * @param Node $node
     */
    public function register(string $name,string $id,Node $node): void
    {
        $this->node[$name][$id][] = $node;
    }


    /**
     * deregister a node from the manager
     * @param $name
     * @param string|null $id
     */
    public function deregister($name,?string $id = null): void
    {
        if ($id) {
            unset($this->node[$name][$id]);
        } else {
            unset($this->node[$name]);
        }
    }

    /**
     * list all node
     * @return array
     */
    public function all()
    {
        return $this->node;
    }
}