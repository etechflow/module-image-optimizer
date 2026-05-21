<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Model\Performance;

/**
 * Tideways / Blackfire span helper. Same shape as the Profiler in every
 * other ETechFlow module. No-op when neither profiler is loaded.
 *
 * Span name convention: all IO spans start with `ETechFlow_IO_` so they
 * group cleanly in Tideways's "Top callees" view.
 */
final class Profiler
{
    public static function start(string $name)
    {
        if (\function_exists('tideways_span_create')) {
            $id = \tideways_span_create('etechflow');
            \tideways_span_annotate($id, ['title' => $name]);
            return $id;
        }
        if (\function_exists('blackfire_span_open')) {
            return \blackfire_span_open($name);
        }
        return null;
    }

    public static function stop($handle): void
    {
        if ($handle === null) {
            return;
        }
        if (\function_exists('tideways_span_finish')) {
            \tideways_span_finish($handle);
            return;
        }
        if (\function_exists('blackfire_span_close')) {
            \blackfire_span_close($handle);
        }
    }
}
