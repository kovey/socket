<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 15:34:05
 *
 */
namespace Kovey\Socket\App\Router;

class Router implements RouterInterface
{
    private string $protobuf;

    private string $handler;

    private string $method;

    public function __construct(string $protobuf, string $router)
    {
        $this->protobuf = $protobuf;
        $info = explode('@', $router);
        $this->handler = $info[1];
        $this->method = $info[0];
    }

    public function getProtobuf() : string
    {
        return $this->protobuf;
    }

    public function getHandler() : string
    {
        return $this->handler;
    }

    public function getMethod() : string
    {
        return $this->method;
    }
}
