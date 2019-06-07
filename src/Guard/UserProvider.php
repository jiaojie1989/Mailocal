<?php

/*
 * Copyright (c) 2019 TAL. All rights reserved.
 */

namespace App\Guard;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Description of UserProvider
 *
 * @author JIAO Jie <thomasjiao@vip.qq.com>
 */
class UserProvider implements UserProviderInterface {

    public function loadUserByUsername($username): UserInterface {
        return new User($username);
    }

    public function refreshUser(UserInterface $user): UserInterface {
        return $user;
    }

    public function supportsClass($class): bool {
        return User::class == $class;
    }

}
