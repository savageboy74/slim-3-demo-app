<?php

namespace Savage\Http\Middleware;

use Slim\App as Site;
use Savage\Http\Util\Cookie;
use Savage\Http\Util\Session;

class AuthMiddleware
{
    protected $site;

    public function __construct(Site $site) {
        $this->site = $site;
    }

    public function __invoke($request, $response, $next) {
        $view = $this->site->getContainer()->view->getEnvironment();

        if(Session::exists($this->site->getContainer()->settings['auth']['session'])) {
            $this->site->auth = $this->site->getContainer()->user->where(
                'id',
                Session::get($this->site->getContainer()->settings['auth']['session'])
            )->first();
        }

        $this->checkRememberStatus($response);

        $view->addGlobal('auth', $this->site->auth);

        return $next($request, $response);
    }

    // I think this does not work.. but why?
    protected function checkRememberStatus($response) {
        if(Cookie::exists($this->site->getContainer()->settings['auth']['remember']) && !$this->site->auth) {
            $data = Cookie::get($this->site->getContainer()->settings['auth']['remember']);
            $credentials = explode('.', $data);

            if(empty(trim($data)) || count($credentials) !== 2) {
                $response->withRedirect($this->site->router()->pathFor('home'));
            } else {
                $identifier = $credentials[0];
                $token = $this->site->getContainer()->util->hash($credentials[1]);

                $user = $this->site->getContainer()->user->where('remember_identifier', $identifier)->first();

                if($user) {
                    if($this->site->getContainer()->util->verifyHash($token, $user->remember_token)) {
                        Session::set($this->site->getContainer()->settings['auth']['session'], $user->id);
                        $this->site->auth = $this->site->getContainer()->user->where('id', $user->id)->first();
                    } else {
                        $user->removeRememberCredentials();
                    }
                }
            }
        }
    }
}
