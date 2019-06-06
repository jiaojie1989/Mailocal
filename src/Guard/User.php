<?php

/*
 * Copyright (c) 2019 TAL. All rights reserved.
 */

namespace App\Guard;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Description of User
 *
 * @author JIAO Jie <thomasjiao@vip.qq.com>
 */
class User implements UserInterface {

    protected $user;

    public function __construct($user) {
        $this->user = $user;
    }

    public function getRoles() {
        return [];
    }

    public function getPassword() {
        return '';
    }

    public function getSalt() {
        return null;
    }

    public function getUsername() {
        return $this->user['name'];
    }

    public function eraseCredentials() {
        
    }

}
