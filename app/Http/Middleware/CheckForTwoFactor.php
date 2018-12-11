<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use App\Models\Setting;

class CheckForTwoFactor
{
    /**
     * Routes to ignore for Two Factor Auth
     */
    const IGNORE_ROUTES =
        [
            'enter-two-factor',
            'validate-two-factor',
            'two-factor-enroll',
            'setup',
            'login',
            //'home',
            'logout'
        ];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        //\Log::debug($request->session()->all());

        // Skip the logic if the user is on the two factor pages or the setup pages
        if (in_array($request->route()->getName(), self::IGNORE_ROUTES)) {
            \Log::debug('HEY: This route ('.$request->route()->getName().') is EXEMPT from 2FA.');
            return $next($request);
        }

        \Log::debug('This route ('.$request->route()->getName().') is NOT EXEMPT from 2FA.');

        // Two-factor is enabled (either optional or required)
        if ($settings = Setting::getSettings()) {

            if (Auth::check() && ($settings->two_factor_enabled != '')) {
                \Log::debug('Two factor is enabled as a setting.');

                // This user is already 2fa-authed
                if ($request->session()->get('2fa_authed')) {
                    \Log::debug('Two factor is ON and the user has already authenticated with 2FA');
                    return $next($request);
                }


                // Two-factor is optional and the user has NOT opted in, let them through
                if (($settings->two_factor_enabled == '1') && (Auth::user()->two_factor_optin != '1')) {
                    \Log::debug('Two factor is OPTIONAL for this user');
                    return $next($request);
                }

                // Otherwise make sure they're enrolled and show them the 2FA code entry screen
                if ((Auth::user()->two_factor_secret != '') && (Auth::user()->two_factor_enrolled == '1')) {
                    \Log::debug('Two factor is ON for this user');
                    return redirect()->route('enter-two-factor')->with('info', 'Please enter your two-factor authentication code.');
                }

                return redirect()->route('two-factor-enroll')->with('info', 'Please enroll a device in two-factor authentication.');
            }
        }

        return $next($request);
    }
}
