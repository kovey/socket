<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 10:14:34
 *
 */
namespace Kovey\Socket\Work;

use Kovey\App\Components\Work;
use Kovey\Event\EventInterface;
use Kovey\Socket\Handler\HandlerAbstract;
use Kovey\Connection\ManualCollectInterface;
use Google\Protobuf\Internal\Message;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Socket\App\Router\RouterInterface;
use Kovey\Socket\App\Router\RoutersInterface;
use Kovey\Socket\Event;
use Kovey\Logger\Logger;
use Kovey\Container\Keyword\Fields;

class Handler extends Work
{
    private RoutersInterface $routers;

    public function setRouters(RoutersInterface $routers) : Handler
    {
        $this->routers = $routers;
        return $this;
    }

    public function addRouter(int | string $code, RouterInterface $router) : Handler
    {
        $this->routers->addRouter($code, $router);
        return $this;
    }

    public function run(EventInterface $event) : Array
    {
        $router = $this->routers->getRouter($event->getPacket()->getAction());
        if (empty($router)) {
            throw new CloseConnectionException('protocol number is error', 1000);
        }
        $class = $router->getProtobuf();
        $protobuf = new $class();
        $base = $event->getPacket()->getBase();
        $protobuf->mergeFromString($event->getPacket()->getMessage());

        $class = $router->getHandler();
        $keywords = $this->container->getKeywords($class, $router->getMethod());
        $locker = $keywords[Fields::KEYWORD_LOCKER] ?? null;
        if (!empty($locker)) {
            if (!$this->locker->lock($event->getFd(), $locker->getKey(), $locker->getExpire())) {
                return array(
                    'class' => $router->getHandler(), 'method' => $router->getMethod(), 'params' => $protobuf->serializeToJsonString(), 
                    'base' => empty($base) ? '' : $base->serializeToJsonString(), 'error' => 'method is locked'
                );
            }
        }

        try {
            $instance = $this->container->get($class, $event->getTraceId(), $event->getSpanId(), $keywords['ext']);
            if (!$instance instanceof HandlerAbstract) {
                throw new CloseConnectionException("$class is not implements HandlerAbstract");
            }

            $instance->setClientIp($event->getIp());

            if ($keywords[Fields::KEYWORD_OPEN_TRANSACTION]) {
                $keywords[Fields::KEYWORD_DATABASE]->beginTransaction();
                try {
                    $result = $this->triggerHandler($instance, $router->getMethod(), $protobuf, $event->getFd(), $base, $event);
                    $keywords[Fields::KEYWORD_DATABASE]->commit();
                } catch (\Throwable $e) {
                    $keywords[Fields::KEYWORD_DATABASE]->rollBack();
                    Logger::writeErrorLog(__LINE__, __FILE__, array(
                        'class' => $router->getHandler(),
                        'method' => $router->getMethod(),
                        'params' => $protobuf->serializeToJsonString(),
                        'error' => $e->getMessage(),
                        'base' => empty($base) ? '' : $base->serializeToJsonString()
                    ));
                    throw $e;
                }
            } else {
                $result = $this->triggerHandler($instance, $router->getMethod(), $protobuf, $event->getFd(), $base, $event);
            }

            if (empty($result)) {
                $result = array();
            }

            $result['class'] = $router->getHandler();
            $result['method'] = $router->getMethod();
            $result['params'] = $protobuf->serializeToJsonString();
            $result['base'] = empty($base) ? '' : $base->serializeToJsonString();

            return $result;
        } catch (\Throwable $e) {
            Logger::writeErrorLog(__LINE__, __FILE__, array(
                'class' => $router->getHandler(),
                'method' => $router->getMethod(),
                'params' => $protobuf->serializeToJsonString(),
                'error' => $e->getMessage(),
                'base' => empty($base) ? '' : $base->serializeToJsonString()
            ));
            throw $e;
        } finally {
            if (!empty($locker)) {
                $this->locker->unlock($event->getFd(), $locker->getKey());
            }

            foreach ($keywords as $value) {
                if (!$value instanceof ManualCollectInterface) {
                    continue;
                }

                $value->collect();
            }
        }
    }

    private function triggerHandler(HandlerAbstract $instance, string $method, Message $message, int $fd, ?Message $base, Event\Handler $event) : Array
    {
        return call_user_func(array($instance, $method), $message, $fd, $base);
    }
}
