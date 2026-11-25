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
/**
 * PS 9 : la connexion au back-office passe par le pare-feu Symfony (form_login), avant tout contrôleur et tout hook.
 * Un écouteur CheckPassportEvent y vérifie le jeton. PS 8 et thirty bees : rien ici (hooks du contrôleur legacy).
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    if (!defined('_PS_VERSION_') || version_compare(_PS_VERSION_, '9.0.0', '<')) {
        return;
    }
    $container->services()
        ->set('cfturnstile.admin_login_listener', 'CfTurnstileAdminLoginListener')
        ->public()
        // Pas d'autoload composer : le fichier est chargé au premier usage.
        ->file(dirname(__DIR__, 2).'/classes/CfTurnstileAdminLoginListener.php')
        ->args([new Symfony\Component\DependencyInjection\Reference('request_stack')])
        ->tag('kernel.event_listener', ['event' => 'Symfony\Component\Security\Http\Event\CheckPassportEvent', 'method' => 'onCheckPassport']);
};
