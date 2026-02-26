<?php

namespace App\Console\Commands;

use App\Services\Bunny\BunnyForwardedLogIngestor;
use Illuminate\Console\Command;

class BunnyLogForwardingListenCommand extends Command
{
    protected $signature = 'bunny:forwarding-listen
        {--protocol=udp : Listener protocol: udp or tcp}
        {--host=0.0.0.0 : Bind host}
        {--port=5514 : Bind port}
        {--token= : Optional shared token required in payload text}';

    protected $description = 'Listen for Bunny forwarded logs (syslog) and store request events locally.';

    public function handle(BunnyForwardedLogIngestor $ingestor): int
    {
        $protocol = strtolower((string) $this->option('protocol'));
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $token = trim((string) $this->option('token'));

        if (! in_array($protocol, ['udp', 'tcp'], true)) {
            $this->error('Unsupported protocol. Use udp or tcp.');

            return self::FAILURE;
        }

        if ($port <= 0) {
            $this->error('Port must be a positive integer.');

            return self::FAILURE;
        }

        $transport = sprintf('%s://%s:%d', $protocol, $host, $port);
        $errno = 0;
        $errstr = '';
        $flags = $protocol === 'udp'
            ? STREAM_SERVER_BIND
            : (STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        $server = @stream_socket_server($transport, $errno, $errstr, $flags);

        if (! is_resource($server)) {
            $this->error("Unable to bind {$transport}: {$errstr} ({$errno})");

            return self::FAILURE;
        }

        stream_set_blocking($server, true);

        $this->info("Listening for Bunny forwarded logs on {$transport}");
        $this->line('Press Ctrl+C to stop.');

        $total = 0;

        while (true) {
            $payload = $protocol === 'udp'
                ? $this->readUdp($server)
                : $this->readTcp($server);

            if ($payload === null) {
                usleep(100000);

                continue;
            }

            if ($token !== '' && ! str_contains($payload, $token)) {
                continue;
            }

            $inserted = $ingestor->ingest($payload);
            $total += $inserted;

            if ($inserted > 0) {
                $this->line("+{$inserted} log row(s) ingested. Total: {$total}");
            }
        }
    }

    private function readUdp($server): ?string
    {
        $peer = null;
        $payload = @stream_socket_recvfrom($server, 65535, 0, $peer);

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        return $payload;
    }

    private function readTcp($server): ?string
    {
        $client = @stream_socket_accept($server, 5);

        if (! is_resource($client)) {
            return null;
        }

        $payload = stream_get_contents($client);
        fclose($client);

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        return $payload;
    }
}
