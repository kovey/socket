<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-04-12 16:32:34
 *
 */
namespace Kovey\Socket\Server;

use Kovey\Library\Exception\BusiException;
use Kovey\Library\Exception\CloseConnectionException;
use Kovey\Library\Exception\KoveyException;
use Kovey\Network\Event;
use Kovey\Logger\Logger;
use Kovey\Logger\Monitor;
use Kovey\Socket\Protocol\ProtocolInterface;
use Kovey\Event\EventManager;
use Google\Protobuf\Internal\Message;
use Kovey\Network\AdapterInterface;
use Kovey\App\Components\Work;
use Kovey\Socket\Handler\PackInterface;
use Kovey\Socket\Event\Handler as EH;

class Receive
{
    private Event\Receive $event;

    private float $begin;

    private int $reqTime;

    private Array $result;

    private string $traceId;

    private string $trace;

    private string $err;

    private string $ip;

    private int $fd;

    private string $service;

    private int | string $action;

    private string $type;

    private ProtocolInterface $packet;

    private string $spanId;

    public function __construct(Event\Receive $event, string $service, ProtocolInterface $packet)
    {
        $this->event = $event;
        $this->ip = $event->getServer()->getClientIP($event->getFd());
        $this->fd = $event->getFd();
        $this->service = $service;
        $this->packet = $packet;
    }

    public function begin() : Receive
    {
        $this->begin = microtime(true);
        $this->reqTime = time();
        $this->result = array();
        $this->traceId = hash('sha256', uniqid($this->fd, true) . random_int(1000000, 9999999));
        $this->trace = '';
        $this->err = '';
        $this->type = 'success';
        $this->action = 0;
        $this->spanId = md5($this->fd . microtime(true));

        return $this;
    }

    public function run(Work $work) : Receive
    {
        try {
            $this->action = $this->packet->getAction();
            $this->result = $work->run(new EH($this->packet, $this->fd, $this->ip, $this->traceId, $this->spanId));
        } catch (CloseConnectionException $e) {
            $this->event->getServer()->close($this->fd);
            $this->type = 'connection_close_exception';
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        } catch (BusiException | KoveyException $e) {
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            $this->type = 'busi_exception';
            Logger::writeBusiException(__LINE__, __FILE__, $e, $this->traceId);
        } catch (\Throwable $e) {
            $this->trace = $e->getTraceAsString();
            $this->err = $e->getMessage();
            $this->type = 'error_exception';
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        }

        return $this;
    }

    public function end(PackInterface $pack) : Receive
    {
        if (empty($this->result) || !isset($this->result['message']) || !isset($this->result['action'])) {
            return $this;
        }

        try {
            $this->event->getServer()->send($pack->pack($this->result['message'], $this->result['action']), $this->fd);
        } catch (CloseConnectionException $e) {
            $this->event->getServer()->close($this->fd);
            Logger::writeExceptionLog(__LINE__, __FILE__, $e, $this->traceId);
        }

        return $this;
    }

    public function monitor() : Receive
    {
        if (!empty($this->result['message'])) {
            if ($this->result['message'] instanceof Message) {
                $this->result['message'] = $this->result['message']->serializeToJsonString();
            }
        }

        $end = microtime(true);
        $content = array(
            'delay' => round(($end - $this->begin) * 1000, 2),
            'request_time' => $this->begin * 10000,
            'action' => $this->action,
            'res_action' => $this->result['action'] ?? 0,
            'class' => $this->result['class'] ?? '',
            'method' => $this->result['method'] ?? '',
            'service' => $this->service,
            'service_type' => 'tcp',
            'packet' => base64_encode($this->event->getData()),
            'type' => $this->type,
            'params' => $this->result['params'] ?? '',
            'response' => $this->result['message'] ?? '',
            'base' => $this->result['base'] ?? '',
            'ip' => $this->ip,
            'time' => $this->reqTime,
            'timestamp' => date('Y-m-d H:i:s', $this->reqTime),
            'minute' => date('YmdHi', $this->reqTime),
            'traceId' => $this->traceId,
            'from' => $this->service,
            'end' => $end * 10000,
            'trace' => $this->trace,
            'err' => empty($this->result['error']) ? $this->err : $this->result['error'],
            'parentId' => 'root',
            'spanId' => $this->spanId,
            'fd' => $this->fd
        );

        Monitor::write($content);
        return $this;
    }
}
