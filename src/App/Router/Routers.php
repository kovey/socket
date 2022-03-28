<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 15:40:55
 *
 */
namespace Kovey\Socket\App\Router;

use Google\Protobuf\Internal\Message;

class Routers implements RoutersInterface
{
    private Array $routers;

    private Message $base;

    public function __construct()
    {
        $this->routers = Array();
    }

    public function addRouter(string | int $code, RouterInterface $router) : RoutersInterface
    {
        $this->routers[$code] = $router;
        return $this;
    }

    public function getRouter(string | int $code) : ?RouterInterface
    {
        return $this->routers[$code] ?? null;
    }

    public function getBase() : Message
    {
        return $this->base;
    }
}
