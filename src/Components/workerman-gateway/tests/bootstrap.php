<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * 开启服务器.
 */
function startServer(): void
{
    // @phpstan-ignore-next-line
    function checkHttpServerStatus(): bool
    {
        $serverStarted = false;
        for ($i = 0; $i < 60; ++$i)
        {
            sleep(1);
            $context = stream_context_create(['http' => ['timeout' => 1]]);
            if ('imi' === @file_get_contents(imiGetEnv('HTTP_SERVER_HOST', 'http://127.0.0.1:13000/'), false, $context))
            {
                $serverStarted = true;
                break;
            }
        }

        return $serverStarted;
    }

    if ('\\' === \DIRECTORY_SEPARATOR)
    {
        $servers = [
            'WorkermanServer'    => [
                'start'         => __DIR__ . '/unit/AppServer/bin/start-workerman.ps1',
                'stop'          => __DIR__ . '/unit/AppServer/bin/stop-workerman.ps1',
                'checkStatus'   => 'checkHttpServerStatus',
            ],
        ];
    }
    else
    {
        $servers = [
            'WorkermanServer'    => [
                'start'         => __DIR__ . '/unit/AppServer/bin/start-workerman.sh',
                'checkStatus'   => 'checkHttpServerStatus',
            ],
            'WorkermanRegisterServer'    => [
                'start'         => __DIR__ . '/unit/AppServer/bin/start-workerman.sh --name register',
            ],
            'WorkermanGatewayServer'    => [
                'start'         => __DIR__ . '/unit/AppServer/bin/start-workerman.sh --name gateway',
            ],
            'SwooleServer' => [
                'start'         => __DIR__ . '/unit/AppServer/bin/start-swoole.sh',
                'stop'          => __DIR__ . '/unit/AppServer/bin/stop-swoole.sh',
                'checkStatus'   => 'checkHttpServerStatus',
            ],
        ];
    }

    $input = new ArgvInput();
    switch ($input->getParameterOption('--testsuite'))
    {
        case 'swoole':
            runTestServer('WorkermanRegisterServer', $servers['WorkermanRegisterServer']);
            runTestServer('WorkermanGatewayServer', $servers['WorkermanGatewayServer']);
            runTestServer('SwooleServer', $servers['SwooleServer']);
            break;
        case 'workerman':
            runTestServer('WorkermanServer', $servers['WorkermanServer']);
            break;
        default:
            throw new \RuntimeException(sprintf('Unknown --testsuite %s', $input->getParameterOption('--testsuite')));
    }

    if ('/' === \DIRECTORY_SEPARATOR)
    {
        register_shutdown_function(function () {
            echo 'Stoping WorkermanServer...', \PHP_EOL;
            shell_exec(<<<CMD
kill `ps -ef|grep "WorkerMan: master process"|grep -v grep|awk '{print $2}'`
CMD);
            echo 'WorkermanServer stoped!', \PHP_EOL, \PHP_EOL;
        });
    }
}

function runTestServer(string $name, array $options): void
{
    // start server
    if ('\\' === \DIRECTORY_SEPARATOR)
    {
        $cmd = 'powershell ' . $options['start'];
    }
    else
    {
        $cmd = 'nohup ' . $options['start'] . ' > /dev/null 2>&1';
    }
    echo "Starting {$name}...", \PHP_EOL;
    shell_exec("{$cmd}");

    if (isset($options['stop']))
    {
        register_shutdown_function(function () use ($name, $options) {
            // stop server
            $cmd = $options['stop'];
            if ('\\' === \DIRECTORY_SEPARATOR)
            {
                $cmd = 'powershell ' . $cmd;
            }
            echo "Stoping {$name}...", \PHP_EOL;
            shell_exec("{$cmd}");
            echo "{$name} stoped!", \PHP_EOL, \PHP_EOL;
        });
    }

    if (isset($options['checkStatus']))
    {
        if (($options['checkStatus'])())
        {
            echo "{$name} started!", \PHP_EOL;
        }
        else
        {
            throw new \RuntimeException("{$name} start failed");
        }
    }
}

startServer();