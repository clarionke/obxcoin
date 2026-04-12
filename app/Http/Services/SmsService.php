<?php
/**
 * Created by PhpStorm.
 * User: bacchu
 * Date: 8/24/17
 * Time: 1:34 PM
 */

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SmsService
{
    protected $client;
    protected string $from;

    public function __construct()
    {
        $sid   = allsetting('twillo_secret_key');
        $token = allsetting('twillo_auth_token');
        $this->from   = allsetting('twillo_number');

        $this->client = new Client($sid, $token);
    }

    public function send($number, $message)
    {
        try {
            $this->client->messages->create($number, [
                'from' => $this->from,
                'body' => $message,
            ]);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return false;
        }

        return true;
    }
}
