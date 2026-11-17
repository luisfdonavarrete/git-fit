<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncUserTimezone
{
    /**
     * Remember the browser's timezone (sent in the `timezone` cookie) on the signed-in user.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $timezone = $request->cookie('timezone');

        if ($user !== null
            && is_string($timezone)
            && $timezone !== $user->timezone
            && in_array($timezone, timezone_identifiers_list(), true)) {
            $user->forceFill(['timezone' => $timezone])->saveQuietly();
        }

        return $next($request);
    }
}
