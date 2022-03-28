<?php

namespace WDK;
/**
 * Class Log - Logging helpers
 * @package MLA_Kit\Controllers\Helpers
 */
class Log
{

    /**
     * writes info to log, with option debug backtrace
     * @param $log
     * @param string $note
     * @param int | bool $levels
     * @param int $deprecated_levels
     */
    public static function Write($log, string $note = "", $levels = 0, int $deprecated_levels = 100): void
    {
        $default_message = empty($note) ? "BACKTRACE>>> " : false;
        if (empty($default_message)) {
            $note = "Note: " . $note . "\n";
        }

        if ($levels === true) {
            //backward compatibility for older debug statements.
            $levels = $deprecated_levels;
        }
        $clean_last = false;
        if (empty($levels)) {
            $levels = 3;
            $clean_last = true;
        } else {
            $levels += 2;
        }

        $debug = debug_backtrace(1, $levels);
        if (!empty($default_message)) {
            $note .= $default_message;
        }
        if($debug[0]['function'] === 'Write') {
            array_shift($debug);
        }

        if($debug[1]['function'] === 'MLA_Log') {
            array_shift($debug);
        }

        if($clean_last) {
            array_pop($debug);
        }
        sort($debug);
        $note_array = [];
        if(!empty($debug) && is_array($debug)) {
            foreach ($debug as $k) {
                //error_log('debug: ' . print_r($k, true) . "\n");

                $note_array[] = $note . $k['file'] . ":" . $k['line'];
            }
        }
        $note = implode("\n ",$note_array);
        error_log($note . "\n". print_r($log, true) . "\n");
    }
}
