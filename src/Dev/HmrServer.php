<?php

declare(strict_types=1);

namespace PCP\Dev;

use PCP\PCP;
use Throwable;

final readonly class HmrServer
{
    public function __construct(
        private PCP $pcp,
    ) {
    }

    public function handle(): never
    {
        $this->sendHeaders();

        $watcher = new HmrFileWatcher($this->pcp->config);
        $since = isset($_GET['since']) && is_string($_GET['since']) ? $_GET['since'] : null;

        try {
            $current = $watcher->fingerprint();
            $this->emit('ready', [
                'fingerprint' => $current,
            ]);

            $changed = $watcher->waitForChange($since ?? $current);

            if ($changed !== null) {
                $this->emit('reload', [
                    'fingerprint' => $changed,
                ]);
            } else {
                $this->emit('ping', [
                    'fingerprint' => $current,
                ]);
            }
        } catch (Throwable $e) {
            $this->emit('error', [
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    private function sendHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $event, array $data): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        flush();
    }
}