<?php

namespace rnwcinv\Managers;

use rnwcinv\utilities\FileManager;

class LogManager
{
    /** @var FileManager */
    private static $fileManager;
    private static $ShouldLog = null;
    const TYPE_ERROR = 10;
    const TYPE_DEBUG = 5;

    static function Initialize()
    {
        self::$fileManager = new FileManager();
    }

    /**
     * @param $type int
     * @param $message string
     */
    static function Log($type, $message)
    {
        if (!self::ShouldLog($type))
            return;

        $line = get_date_from_gmt(date('c')) . " - [" . \strtoupper($type == self::TYPE_ERROR ? 'ERROR' : 'DEBUG') . "] --> " . $message . "\r\n";

        $path = self::GetLogFilePath();

        \file_put_contents($path, $line, FILE_APPEND);
    }

    static function SetShouldLog($shouldLog)
    {
        self::$ShouldLog = $shouldLog;
    }

    static function LogError($message)
    {
        self::Log(self::TYPE_ERROR, $message);
    }

    static function LogDebug($message)
    {
        self::Log(self::TYPE_DEBUG, $message);
    }

    private static function ShouldLog($type = null)
    {
        if (self::$ShouldLog === null)
            self::$ShouldLog = get_option('rnwcinv_enable_log') == "1";
        return self::$ShouldLog;
    }

    static function RemoveLog()
    {
        $path = self::GetLogFilePath();
        if (\file_exists($path))
            \unlink($path);
    }

    public static function GetLogFilePath()
    {
        return self::$fileManager->GetLoggerPath() . '/log.txt';
    }
}
