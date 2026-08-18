<?php
declare(strict_types=1);

/**
 * The local login page, which never routes anywhere else.
 *
 * PHP version 7.4+
 *
 * @category Index_Page
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 * @version  1.0
 */

/*
 * A FOG server can be configured to send everyone straight to an external
 * identity provider (fog-plugins#17), and that setting can lock every
 * administrator out permanently: if the provider is unreachable, its
 * certificate has expired, discovery breaks, or it is simply misconfigured,
 * an unconditional redirect means there is no way back in through a browser
 * -- including for the local break-glass account that exists for exactly
 * this situation.
 *
 * So this file. It is the equivalent of ServiceNow's login.do: one URL that
 * always renders FOG's own username and password form, whatever any provider
 * or plugin would rather happen.
 *
 *     https://<fog>/fog/management/login.php
 *
 * The guarantee is STRUCTURAL, not a flag somebody remembers to honour.
 * index.php only offers the LOGIN_PAGE_REDIRECT hook when this constant is
 * absent, so a redirect listener is not consulted-and-overruled here -- it is
 * never reached. A plugin cannot opt back in, and a plugin that is broken,
 * half-installed or throwing cannot take this page down with it, because it
 * is not asked anything.
 *
 * Everything else is index.php's, deliberately: the same bootstrap, the same
 * ProcessLogin, the same CSRF token, the same session. A second copy of the
 * login form is a second place for an authentication bug to live, and the
 * one thing a break-glass page must not be is subtly different from the real
 * one.
 */
define('FOG_LOCAL_LOGIN', true);

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
