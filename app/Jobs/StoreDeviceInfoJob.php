<?php

namespace App\Jobs;

use App\Models\UserInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Jenssegers\Agent\Agent;

class StoreDeviceInfoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ip;
    protected $agent;
    protected $location;
    protected $deviceInfo; 
    /**
     * Create a new job instance.
     */
    public function __construct(string $ip,  $agent, $location)
    {
        $this->ip = $ip;
        $this->agent = new Agent();
        if (is_object($agent) && isset($agent->userAgent)) {
            $this->agent->setUserAgent($agent->userAgent);
        } elseif (is_object($agent) && isset($agent->{'HTTP_USER_AGENT'})) {
            $this->agent->setUserAgent($agent->{'HTTP_USER_AGENT'});
        } elseif (is_array($agent) && isset($agent['HTTP_USER_AGENT'])) {
            $this->agent->setUserAgent($agent['HTTP_USER_AGENT']);
        } elseif (is_string($agent)) {
            $this->agent->setUserAgent($agent);
        }
        $this->deviceInfo = (object) [
            'device' => $this->agent->device() ?: 'Unknown',
            'browser' => $this->agent->browser() ?: 'Unknown',
            'platform' => $this->agent->platform() ?: 'Unknown'
        ];
        $this->location = $location;
        // log the location
        if (is_object($location)) {
            Log::info("Location data: Country - {$location->countryName}, City - {$location->cityName}, Latitude - {$location->latitude}, Longitude - {$this->location->longitude}");
        } else {
            Log::info("Location data: Unknown");
        }
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $country = $this->location && is_object($this->location) ? $this->location->countryName : 'Unknown';
            $city = $this->location && is_object($this->location) ? $this->location->cityName : 'Unknown';
            $latitude = $this->location && is_object($this->location) ? $this->location->latitude : null;
            $longitude = $this->location && is_object($this->location) ? $this->location->longitude : null;

            $data = [
                'device' => $this->deviceInfo->device,
                'browser' => $this->deviceInfo->browser,
                'platform' => $this->deviceInfo->platform,
                'user_id' => auth()->id(),
                'country' => $country,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'date' => now(),
                'updated_at' => now(),
            ];

            $existing = UserInfo::where('ip_address', $this->ip)->first();

            if ($existing) {
                $existing->update($data);
            } else {
                $data['ip_address'] = $this->ip;
                $data['created_at'] = now();
                UserInfo::create($data);
            }

        } catch (\Exception $e) {
            Log::error("Device info error for IP {$this->ip}: {$e->getMessage()}");
        }
    }
}
