<?php

namespace ProcessMaker\Http\Middleware;

use Closure;

class XFrameOptions
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $xframeOptions = env('X_FRAME_OPTIONS', 'SAMEORIGIN');

        if (false !== strpos($xframeOptions, 'ALLOW-FROM')) {
            $url = trim(str_replace('ALLOW-FROM', '', $xframeOptions));
            $response->header('Content-Security-Policy', 'frame-ancestors ' . $url);
        }

        $response->header('X-Frame-Options', $xframeOptions);

        return $response;
    }
}