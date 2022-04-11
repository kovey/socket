<?php
/**
 * @description tcp server
 *
 * @package Server
 *
 * @author kovey
 *
 * @time 2019-11-13 14:43:19
 *
 */
namespace Kovey\Socket\Server;

use Kovey\Socket\Protocol\ProtocolInterface;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Network\Server as NS;
use Kovey\Network\AdapterInterface;
use Kovey\App\Components\Work;
use Kovey\App\Components\ServerInterface;
use Kovey\Socket\Handler\PackInterface;

class Server implements ServerInterface
{
    private AdapterInterface $server;

    private Array $config;

    private Handler $handler;

    public function __construct(Array $config)
    {
        $this->config = $config;
        $this->initLog();
        $this->server = NS::factory($this->config['socket_type'], $this->config);
        $this->handler = new Handler(($this->config['monitor_open'] ?? 'On') == 'On', $this->config['name']);
        $this->server->setReceive($this->handler);
    }

    private function initLog()
    {
        $logDir = dirname($this->config['pid_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        if (!is_dir($this->config['logger_dir'] . '/server')) {
            mkdir($this->config['logger_dir'] . '/server', 0777, true);
        }
    }

    public function setWork(Work $work) : self
    {
        $this->handler->setWork($work);
        return $this;
    }

    public function setPack(PackInterface $pack) : self
    {
        $this->handler->setPack($pack);
        return $this;
    }

    public function getServer() : AdapterInterface
    {
        return $this->server;
    }

    public function getServ() : \Swoole\Server
    {
        return $this->server->getServ();
    }

    public function on(string $event, callable | Array $callback) : ServerInterface
    {
        return $this;
    }

    /**
     * @description set config options
     *
     * @param string $key
     *
     * @param mixed $val
     *
     * @return Server
     */
    public function setOption(string $key, mixed $val) : Server
    {
        $this->server->getServ()->set(array($key => $val));
        return $this;
    }

    /**
     * @description send data to client
     *
     * @param mixed $packet
     *
     * @param int $fd
     *
     * @return bool
     */
    public function send(mixed $packet, int $action, int $fd) : bool
    {
        if (!$this->server->exist($fd)) {
            throw new CloseConnectionException('connect is not exist');
        }

        return $this->server->send($this->handler->getPack()->pack($packet, $action), $fd);
    }

    public function close(int $fd) : bool
    {
        return $this->server->close($fd);
    }

    public function start() : void
    {
        $this->server->start();
    }
}
