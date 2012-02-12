<?php
/**
 * Simple logger that prints datetime, app name, PID, and message level
 * Uses PHP message levels to preserve comparisons (the constants such as LOG_ALERT, etc.)
 *
 * @version    $Id: edit.php 1169 2011-10-05 19:59:43Z rick.jensen $   
 * @author Stephan Ohlsson
 */
class Logger {
    private $cli;
    private $log_file;
    private $log_level;
    private $log_handle;
    private $app_name;
    public function __construct($log_file = '/var/log/onerain/data-agents-GetHADS.log', $app_name = 'php', $cli = TRUE, $log_level = LOG_INFO) {
		date_default_timezone_set('UTC');
        //echo 'Constructing logger...';
        $this->log_handle = fopen($log_file, 'a');
        $this->log_file = $log_file;
        $this->cli = $cli;
        $this->app_name = $app_name;
		$log_level_alias = $this->parse_level($log_level);
        $this->log_level = $log_level_alias;
    }
    public function __destruct() {
        //echo 'Destructing logger...';
        fclose($this->log_handle);
    }
    public function log($message_level = LOG_INFO, $message) {
        if ($this->log_level >= $message_level) {
            if (is_array($message)) {
                $message = print_r($message, TRUE);
            }
	    $date = date('Y-m-d H:i:s', time());
		$message_level = $this->reverse_parse_level($message_level);
            fwrite($this->log_handle, sprintf('[%12s][%10s][%8u][%6s] %s' . PHP_EOL, $date, $this->app_name, getmypid(), $message_level, $message));
            //echo $message . PHP_EOL;
        }
    }
    public static function parse_level($level) {
        switch ($level) {
            case 'ALERT':
                return LOG_ALERT;
                break;
            case 'CRIT':
                return LOG_CRITICAL;
                break;
            case 'ERROR':
                return LOG_ERR;
                break;
            case 'WARNING':
                return LOG_WARNING;
                break;
            case 'NOTICE':
                return LOG_NOTICE;
                break;
            case 'INFO':
                return LOG_INFO;
                break;
            case 'DEBUG':
                return LOG_DEBUG;
                break;
            default:
                return LOG_INFO;
        }
    }
    public static function reverse_parse_level($level) {
        switch ($level) {
            case 1:
                return 'ALERT';
                break;
            case 2:
                return 'CRIT';
                break;
            case 3:
                return 'ERROR';
                break;
            case 4:
                return 'WARNING';
                break;
            case 5:
                return 'NOTICE';
                break;
            case 6:
                return 'INFO';
                break;
            case 7:
                return 'DEBUG';
                break;
            default:
                return 'ERROR';
        }
    }
}
?>
