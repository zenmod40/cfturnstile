<?php
/**
 * Cloudflare Turnstile pour PrestaShop 1.6 à 9 et thirty bees
 *
 * @author    ZM40 — Nicolas Michaud (Magic Garden)
 * @copyright 2026 Nicolas Michaud — ZM40 / Magic Garden
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__).'/cfturnstile.php';

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/** PS 9 : connexion au back-office par e-mail et mot de passe (pas la reconnexion « rester connecté »). */
class CfTurnstileAdminLoginListener
{
    private $requests;

    public function __construct(RequestStack $requests)
    {
        $this->requests = $requests;
    }

    public function onCheckPassport(CheckPassportEvent $event)
    {
        if (!$event->getPassport()->hasBadge(PasswordCredentials::class)) {
            return;
        }
        $module = Module::getInstanceByName('cfturnstile');
        $error = $module instanceof CfTurnstile && $module->active ? $module->adminLoginError() : null;
        if ($error === null) {
            return;
        }
        // La page de connexion remplace tout échec par « identifiants incorrects » : notre message en plus.
        $request = $this->requests->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', $error);
        }
        throw new CustomUserMessageAuthenticationException($error);
    }
}
