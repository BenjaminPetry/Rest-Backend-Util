<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
class Log
{
    private static $log_stack; // all the log information of this request

    public function __construct() // necessary for the ::log method to work
    {
    }

    /**
     * Returns all log information of this request
     */
    public static function getLogStack()
    {
        return self::$log_stack;
    }

    public static function exception($exception)
    {
        $message = "----------------------------------------------------\r\n";
        $message .= get_class($exception)."\r\n";
        $message .= "Time: ".Date("d.m.Y H:i:s")."\r\n";
        $message .= "File: ".$exception->getFile()."\r\n";
        $message .= "Line: ".$exception->getLine()."\r\n";
        $message .= "Message: ".$exception->getMessage()."\r\n";
        $message .= "Trace:\n".
                str_replace("\n", "\r\n", $exception->getTraceAsString())."\r\n";
        self::write($message);
        
        if (!IS_LOCALHOST) {
            Mail::send(CONFIG_EMAIL_LOG_RECEIVER, "Exception in ".CONFIG_NAME, $message);
        }
    }

    /**
     * Logs the content only if DEBUG is defined
     */
    public static function debug($content)
    {
        if (!VERBOSE) {
            return;
        }
        self::log("DEBUG: ".print_r($content, true));
    }

    public static function log($content)
    {
        self::write("<br>\n".date("d-m-Y H:i:s")." ".$content);
    }

    private static function write($message)
    {
        self::$log_stack .=$message;
        $logfile = PATH_LOG."/".Date("Ymd")."_log.txt";
        $handle = fopen($logfile, 'a+');
        if ($handle) {
            fwrite($handle, $message);
            fclose($handle);
            chmod($logfile, 0666);
        }
    }
}
