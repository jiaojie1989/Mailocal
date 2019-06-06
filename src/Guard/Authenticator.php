<?php

/*
 * Copyright (c) 2019 TAL. All rights reserved.
 */

namespace App\Guard;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Description of Authenticator
 *
 * @author JIAO Jie <thomasjiao@vip.qq.com>
 */
class Authenticator extends AbstractGuardAuthenticator {

    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getCredentials(Request $request) {
        if ($request->query->has('token')) {
            return ['token' => $request->query->get('token')];
        }
        return [];
    }

    public function getUser($credentials, UserProviderInterface $userProvider = null) {
        return $this->getUserInfo($credentials);
    }

    public function checkCredentials($credentials, UserInterface $user) {
        return true; // 注意：对 OAuth2 来说到此步已经拿到 openId，其实已经算登录成功了，直接返回 true
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) {
        $redirectUrl = sprintf("https://service.100tal.com/sso/login/%s", $this->clientId);
        return new RedirectResponse($redirectUrl);
//        return new Response($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey) {
        // do nothing 
    }

    public function supportsRememberMe() {
        return false;
    }

    public function start(Request $request, AuthenticationException $authException = null) {
        $redirectUrl = sprintf("https://service.100tal.com/sso/login/%s", $this->clientId);
        return new RedirectResponse($redirectUrl);
    }

    private function getUserInfo(array $credentials) {
        $session = new Session();
        if (empty($credentials)) {
            $user = $session->get('user');
            if (empty($user)) {
                throw new AuthenticationException('session not exist');
            }
        } else {
            $tkResp   = file_get_contents(sprintf("https://api.service.100tal.com/basic/get_ticket?appid=%s&appkey=%s", $this->clientId, $this->clientSecret));
            $tkInfo   = json_decode($tkResp, true);
            $tk       = $tkInfo['ticket'];
            $userInfo = file_get_contents(sprintf("http://api.service.100tal.com/sso/verify?token=%s&ticket=%s", $credentials['token'], $tk));
            $user     = json_decode($userInfo, true);
            if ($user['errcode']) {
                throw new AuthenticationException('Auth server is down');
            }

            $session->set('user', $user);
        }

        return new User($user);
    }

    private function getOpenIdFromCredentials(array $credentials) {
        $url = sprintf(
                'https://api.weixin.qq.com/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code',
                $this->clientId,
                $this->clientSecret,
                $credentials['code']
        );

        try {
            $info = json_decode(file_get_contents($url), true);
        } catch (\Exception $ex) {
            throw new AuthenticationException('OAuth server is down', $ex);
        }

        if (empty($info['openid'])) {
            throw new AuthenticationException('OAuth code is not valid');
        }

        return $info['openid'];
    }

    public function supports(Request $request): bool {
        $session = new Session();
        $user    = $session->get('user');
        if (empty($user)) {
            return true;
        }
        return false;
    }

}
