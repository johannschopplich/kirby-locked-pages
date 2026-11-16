<?php

declare(strict_types = 1);

namespace JohannSchopplich\LockedPages;

use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Http\Range;
use Kirby\Http\Response;

/**
 * Sends a file, or the byte range a `Range` header asks for, without
 * reading it into memory.
 *
 * `Response::file()` reads the whole file into memory, which the media
 * folder only pays for once per file. A locked file passes through PHP on
 * every request – including a video that browsers request as `bytes=0-` –
 * so a file larger than `memory_limit` would fail.
 */
final class FileStream extends Response
{
    private const CHUNK_SIZE = 1024 * 1024;

    private int $start = 0;
    private int $length;

    public function __construct(
        private string $root,
        array $headers = []
    ) {
        $size = (int)filesize($root);
        $range = App::instance()->request()->header('Range');
        $this->length = $size;
        $code = 200;
        $type = F::extensionToMime(F::extension($root));

        // Kirby 5.3 added byte ranges; earlier versions send the whole file.
        if (is_string($range) && class_exists(Range::class)) {
            $bounds = Range::parse($range, $size);

            if ($bounds === false) {
                $this->length = 0;
                parent::__construct(code: 416, headers: ['Content-Range' => 'bytes */' . $size]);
                return;
            }

            [$this->start, $end] = $bounds;
            $this->length = $end - $this->start + 1;
            $code = 206;
            $headers['Content-Range'] = 'bytes ' . $this->start . '-' . $end . '/' . $size;
        }

        if ($type === null) {
            $type = 'text/plain';
            $headers['X-Content-Type-Options'] = 'nosniff';
        }

        parent::__construct(code: $code, type: $type, headers: [
            'Accept-Ranges' => 'bytes',
            ...$headers,
            'Content-Length' => (string)$this->length
        ]);
    }

    /**
     * Sends the headers with an empty body, then echoes the bytes in chunks.
     */
    public function send(): string
    {
        parent::send();

        // `stream_copy_to_stream()` hands the output layer the whole range at
        // once, which an active output buffer then holds in memory.
        $handle = fopen($this->root, 'rb');
        fseek($handle, $this->start);
        $remaining = $this->length;

        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(self::CHUNK_SIZE, $remaining));
            echo $chunk;
            flush();
            $remaining -= strlen($chunk);
        }

        fclose($handle);

        return '';
    }
}
