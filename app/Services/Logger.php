<?php namespace App\Services;
use Illuminate\Support\Facades\Log;

/**  * Logger class  */
class Logger
{
    public function __construct()
    {
        // $this->path = env('ERROR_LOG_PATH');
    }

    public function log($type, $text = '', $timestamp = true)
    {
        try {
            $logPrint = env('LOG_PRINT_ENABLE') ?? 1;
            if ($logPrint == 1) {
                if(gettype($text) == 'array'){
                    $text = json_encode($text);
                }
                if ($timestamp) {
                    $datetime = date("d-m-Y H:i:s");
                    $text = "$datetime, $type: $text \r\n\r\n";
                } else {
                    $text = "$type\r\n\r\n";
                }
                Log::info($text);
            }
        } catch (\Exception $e) {
            Log::info("log exception");
        }
    }

}

