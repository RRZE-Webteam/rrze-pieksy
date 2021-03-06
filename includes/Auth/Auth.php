<?php

namespace RRZE\Pieksy\Auth;

defined('ABSPATH') || exit;

use RRZE\Pieksy\Functions;

abstract class Auth
{
    protected $uid = null;

    protected $mail = null;

    protected $personAttributes = null;

    abstract public function isAuthenticated(): bool;

    abstract public function setAttributes();

    abstract public function getCustomerData(): array;

    abstract public function logout();

    public static function tryLogIn()
    {
        $authNonce = sprintf('require-auth=%s', wp_create_nonce('require-auth'));
        $queryStr = Functions::getQueryStr();
        $redirectUrl = trailingslashit(get_permalink()) . ($queryStr ? '?' . $queryStr . '&' : '?') . $authNonce;
        header('HTTP/1.0 403 Forbidden');
        wp_redirect($redirectUrl);
        exit;
    }
}
