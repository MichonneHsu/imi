<?php

declare(strict_types=1);

namespace Imi\Workerman\Server\Udp;

use Imi\Bean\Annotation\Bean;
use Imi\Event\Event;
use Imi\RequestContext;
use Imi\Server\Protocol;
use Imi\Server\UdpServer\Contract\IUdpServer;
use Imi\Workerman\Server\Base;
use Imi\Workerman\Server\UdpServer\Message\PacketData;
use Workerman\Connection\UdpConnection;

/**
 * @Bean("WorkermanUdpServer")
 */
class Server extends Base implements IUdpServer
{
    /**
     * 获取协议名称.
     *
     * @return string
     */
    public function getProtocol(): string
    {
        return Protocol::UDP;
    }

    /**
     * 是否为长连接服务
     *
     * @return bool
     */
    public function isLongConnection(): bool
    {
        return false;
    }

    /**
     * 绑定服务器事件.
     *
     * @return void
     */
    protected function bindEvents()
    {
        parent::bindEvents();

        $this->worker->onMessage = function (UdpConnection $connection, string $data) {
            $requestContext = RequestContext::getContext();
            $requestContext['server'] = $this;
            $requestContext['connection'] = $connection;
            $packetData = $requestContext['connection'] = new PacketData($connection, $data);
            Event::trigger('IMI.WORKERMAN.SERVER.UDP.MESSAGE', [
                'server'     => $this,
                'connection' => $connection,
                'data'       => $data,
                'packetData' => $packetData,
            ], $this);
        };
    }

    /**
     * 获取实例化 Worker 用的协议.
     *
     * @return string
     */
    protected function getWorkerScheme(): string
    {
        return 'udp';
    }

    /**
     * 向客户端发送消息.
     *
     * @param string $ip
     * @param int    $port
     * @param string $data
     *
     * @return bool
     */
    public function sendTo(string $ip, int $port, string $data): bool
    {
        $connection = new UdpConnection($this->worker->getMainSocket(), $ip . ':' . $port);

        return (bool) $connection->send($data);
    }
}