<?php
namespace ProcessMaker\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use League\OAuth2\Server\ResourceServer;
use ProcessMaker\Models\User;
use ProcessMaker\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use ProcessMaker\Traits\HasControllerAddons;
use Illuminate\Support\Facades\Auth;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class LoginController extends Controller
{
    use HasControllerAddons;
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    protected $redirectTo = '/requests';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except(['logout', 'keepAlive']);
    }

    public function loginWithIntendedCheck(Request $request) {
        $intended = redirect()->intended()->getTargetUrl();
        if ($intended) {
            // Check if the route is a fallback, meaning it's invalid (like favicon.ico)
            $route = app('router')->getRoutes() ->match(
                app('request') ->create($intended)
            );
            if ($route->isFallback) {
                $intended = false;
            }

            // Getting intended deletes it, so put in back
            $request->session()->put('url.intended', $intended);
        }
        
        // Check the status of the user
        $user = User::where('username', $request->input('username'))->firstOrFail();
        if ($user->status === 'INACTIVE') {
            return redirect()->back();
        }

        $addons = $this->getPluginAddons('command', []);
        foreach($addons as $addon) {
            if(array_key_exists('command', $addon)) {
                $command = $addon['command'];
                $command->execute($request, $request->input('username'));
            }
        }

        return $this->login($request);
    }

    public function loginWithToken(Request $request)
    {
        $token = $request->get('loginToken');
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
        $serverRequest = $server->validateAuthenticatedRequest($psr);

        Auth::logout();
        Auth::loginUsingId($serverRequest->getAttribute('oauth_user_id'), true);

        return redirect('/');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    public function keepAlive()
    {
        return response('', 204);
    }

    protected function authenticated(Request $request, $user)
    {
        if (env('LOGOUT_OTHER_DEVICES', false)) {
            Auth::logoutOtherDevices($request->input('password'));
        }
    }

    public function loggedOut(Request $request)
    {
        $response = redirect(route('login'));
        if ($request->has('timeout')) {
            $response->with('timeout', true);
        }
        return $response;
    }
}
