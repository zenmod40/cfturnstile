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

/**
 * Vérification d'un jeton Turnstile auprès de Cloudflare (siteverify).
 *
 * Résultat : ok (laisser passer), open (laissé passer faute de réponse fiable de Cloudflare), codes (erreurs Cloudflare).
 * Cloudflare injoignable, ou clé secrète refusée : on laisse passer et on le note. Une panne de Cloudflare ou une
 * erreur de réglage ne doit fermer ni la boutique aux clients ni le back-office.
 */
class CfTurnstileVerifier
{
    const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    const TIMEOUT = 5;

    /** @var string adresse appelée (remplaçable par les tests) */
    public static $url = self::URL;

    /** Erreurs de configuration ou de Cloudflare lui-même, pas du visiteur. */
    const NOT_VISITOR = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

    /**
     * @param bool $strict essai depuis le back-office : une clé refusée est un échec, pas un passage libre
     * @return array ['ok' => bool, 'open' => bool, 'codes' => string[]]
     */
    public static function verify($secret, $token, $ip, $strict = false)
    {
        if ($token === '') {
            return ['ok' => false, 'open' => false, 'codes' => ['missing-input-response']];
        }
        if (strlen($token) > 2048) {
            return ['ok' => false, 'open' => false, 'codes' => ['invalid-input-response']];
        }
        $raw = self::post(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
        $answer = $raw === false ? null : json_decode($raw, true);
        if (!is_array($answer) || !isset($answer['success'])) {
            return ['ok' => !$strict, 'open' => true, 'codes' => [$raw === false ? 'cloudflare-injoignable' : 'reponse-illisible']];
        }
        $codes = isset($answer['error-codes']) && is_array($answer['error-codes']) ? array_values(array_map('strval', $answer['error-codes'])) : [];
        if ($answer['success']) {
            return ['ok' => true, 'open' => false, 'codes' => $codes];
        }
        if (!$strict && array_intersect($codes, self::NOT_VISITOR)) {
            return ['ok' => true, 'open' => true, 'codes' => $codes];
        }
        return ['ok' => false, 'open' => false, 'codes' => $codes ?: ['refuse']];
    }

    /** @return string|false */
    private static function post(array $fields)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init(self::$url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT, CURLOPT_TIMEOUT => self::TIMEOUT,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $raw !== false && $status > 0 && $status < 500 ? $raw : false;
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($fields), 'timeout' => self::TIMEOUT, 'ignore_errors' => true,
        ]]);
        return @file_get_contents(self::$url, false, $context);
    }
}
