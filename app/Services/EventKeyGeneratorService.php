<?php

namespace App\Services;

use App\Models\Event;

class EventKeyGeneratorService
{
    public function generateKey()
    {
        $lastEvent = Event::orderBy('id', 'desc')->first();
        $lastKey = $lastEvent ? $lastEvent->event_key : null;

        if (!$lastKey) {
            return 'AA00001';
        }

        return $this->incrementKey($lastKey);
    }

    private function incrementKey($key)
    {
        $prefix = substr($key, 0, 2);
        $number = intval(substr($key, 2));

        if ($number === 99999) {
            $prefix = $this->incrementPrefix($prefix);
            $number = 0;
        }

        $number++;

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    private function incrementPrefix($prefix)
    {
        if ($prefix === 'ZZ') {
            throw new \Exception('Key range exhausted');
        }

        $first = $prefix[0];
        $second = $prefix[1];

        if ($second === 'Z') {
            $first = chr(ord($first) + 1);
            $second = 'A';
        } else {
            $second = chr(ord($second) + 1);
        }

        return $first . $second;
    }
}

