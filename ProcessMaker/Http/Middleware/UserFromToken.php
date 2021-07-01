<?php

namespace ProcessMaker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use League\OAuth2\Server\ResourceServer;
use ProcessMaker\Models\User;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class UserFromToken
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->get('loginToken');
        if (empty($token)) {
            // Process next
            return $next($request);
        }

        $request->headers->add(['Authorization' => 'Bearer ' . $token]);

        $psr = (new PsrHttpFactory(
            new ServerRequestFactory,
            new StreamFactory,
            new UploadedFileFactory,
            new ResponseFactory
        ))->createRequest($request);

        /** @var ResourceServer $server */
        $server = app(\League\OAuth2\Server\ResourceServer::class);

        /** @var \Laminas\Diactoros\ServerRequest $serverRequest */
        try {
            $serverRequest = $server->validateAuthenticatedRequest($psr);
        } catch (\Exception $e) {
            // Process next
            return $next($request);
        }

        $user = User::where('id', $serverRequest->getAttribute('oauth_user_id'))->firstOrFail();
        if ($user->status === 'INACTIVE') {
            // Process next
            return $next($request);
        }

        Auth::login($user);

        // Process next
        return $next($request);
    }
}
