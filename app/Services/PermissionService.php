<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class PermissionService
{
    /**
     * Check single permission
     */
    public function has(string $permission): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false; // user login ન હોય તો false
        }

        return $user->can($permission);
    }

    /**
     * Check multiple permissions at once
     * returns array ['PermissionName' => true/false, ...]
     */
    public function check(array $permissions): array
    {
        $user = Auth::user();

        $result = [];
        // foreach ($permissions as $permission) {
        //     $result[$permission] = $user ? $user->can($permission) : false;
        // }
         foreach ($permissions as $permission) {
            // Auth user pass check
            if (auth()->check() && auth()->user()->hasPermissionTo($permission)) {
                $result[$permission] = true;
            } else {
                // by default false
                $result[$permission] = false;
            }
        }

        return $result;
    }
}
