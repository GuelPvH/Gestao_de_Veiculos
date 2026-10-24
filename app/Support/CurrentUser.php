<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CurrentUser
{
    public static function get(?Request $request = null): User
    {
        $user = $request !== null ? $request->user() : Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
