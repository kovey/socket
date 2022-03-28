<?php
/**
 *
 * @description bootstrap
 *
 * @package     App\Bootstrap
 *
 * @time        Tue Sep 24 09:00:10 2019
 *
 * @author      kovey
 */
namespace Kovey\Socket\App\Bootstrap;

use Kovey\Socket\App\App;
use Kovey\Socket\Server\Server;
use Kovey\Socket\App\Router\Routers;

class BaseInit
{
    /**
     * @description init app
     *
     * @param App $app
     *
     * @return void
     */
    public function __initApp(App $app) : void
    {
        $app->registerServer(new Server($app->getConfig()['server']))
            ->registerRouters(new Routers());
    }
}
