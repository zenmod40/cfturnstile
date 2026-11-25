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
 * Cloudflare Turnstile : vérification anti-robot (le plus souvent invisible) sur les formulaires de la boutique et sur
 * la connexion au back-office, sans aucun override.
 *
 * thirty bees 1.5 / 1.7 (et PS 1.6) : un seul point de contrôle, actionDispatcher, avant que le contrôleur ne traite
 * le formulaire.
 * PrestaShop 1.7, 8 et 9 : les hooks natifs de chaque formulaire (création de compte, newsletter, connexion au
 * back-office) ; actionFrontControllerInitBefore pour ceux qui n'en ont pas (connexion client, mot de passe oublié,
 * contact) ; sous PS 9, la connexion au back-office passe par Symfony : écouteur CheckPassportEvent
 * (config/admin/services.php). Là où la version installée n'a pas encore le hook natif (PS 1.7.0 à 1.7.6, commande
 * en invité avant PS 8.2.1), actionDispatcher, comme sous thirty bees : voir dispatched().
 *
 * Tant que les clés n'ont pas été vérifiées par un essai réussi (onglet Réglages), rien n'est contrôlé :
 * une clé fausse ne peut pas fermer la connexion au back-office.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/CfTurnstileVerifier.php';
require_once __DIR__.'/lib/zm40/Zm40Common.php';

class CfTurnstile extends Module
{
    /**
     * thirty bees / PS 1.6 : formulaire => [contrôleur ('' : toute page de la boutique), champs envoyés qui déclenchent
     * son traitement, bouton devant lequel placer le widget].
     */
    const FORMS_TB = [
        'login'    => ['AuthController', ['SubmitLogin'], '#login_form #SubmitLogin'],
        'create'   => ['AuthController', ['submitAccount', 'submitGuestAccount'], '#submitAccount, #submitGuestAccount'],
        'password' => ['PasswordController', ['email'], '#form_forgotpassword button[type=submit]'],
        'contact'  => ['ContactController', ['submitMessage'], '#submitMessage'],
        'admin'    => ['AdminLoginController', ['submitLogin', 'submitForgot'], '#login_form button[name=submitLogin], #forgot_password_form button[type=submit]'],
        // blocknewsletter : bloc présent sur toutes les pages, il traite l'envoi quelle que soit la page.
        'newsletter' => ['', ['submitNewsletter'], '[name=submitNewsletter]'],
    ];
    /**
     * PrestaShop 1.7 / 8 / 9 : formulaire => [pages (php_self) où il est traité, champs envoyés qui déclenchent son traitement,
     * bouton devant lequel placer le widget ('' : emplacement prévu par le thème, hook d'affichage natif)].
     */
    const FORMS_PS = [
        'login'    => [['authentication', 'order'], ['submitLogin'], '#login-form button[type=submit]'],
        'create'   => [['registration', 'authentication', 'order'], ['submitCreate'], ''], // authentication : PS 1.7
        'password' => [['password'], ['email'], 'form.forgotten-password button[type=submit]'],
        'contact'  => [['contact'], ['submitMessage'], '.contact-form [type=submit][name=submitMessage]'],
        'admin'    => [[], ['submitLogin', 'submitForgot'], ''], // bouton : voir forms()
        'newsletter' => [[], ['submitNewsletter'], ''],
    ];
    const ADMIN_PS8 = '#login_form button[name=submitLogin], #forgot_password_form button[type=submit]';
    const ADMIN_PS9 = 'button[name=submit_login], form[name=request_password_reset] button[type=submit]';
    const LABELS = [
        'login'    => 'Connexion client',
        'create'   => 'Création de compte',
        'password' => 'Mot de passe oublié',
        'contact'  => 'Formulaire de contact',
        'admin'    => 'Connexion au back-office',
        'newsletter' => 'Inscription à la newsletter',
    ];
    const HOOKS_TB = ['actionDispatcher', 'displayHeader', 'actionAdminLoginControllerSetMedia'];
    /** Les hooks de toutes les versions sont inscrits : une boutique mise à jour (1.7 → 8 → 9) reste protégée. */
    const HOOKS_PS = [
        'actionDispatcher', 'actionFrontControllerSetMedia', 'actionFrontControllerInitBefore',
        'displayCustomerAccountForm', 'actionSubmitAccountBefore',
        'displayNewsletterRegistration', 'actionNewsletterRegistrationBefore',
        'actionAdminLoginControllerSetMedia', 'actionAdminLoginControllerLoginBefore', 'actionAdminLoginControllerForgotBefore', // PS 8
        'displayAdminLogin', 'actionEmployeeRequestPasswordResetForm', // PS 9
    ];
    const THEMES = ['auto' => 'Automatique', 'light' => 'Clair', 'dark' => 'Sombre'];
    const SIZES = ['normal' => 'Normale (300 px)', 'flexible' => 'Largeur du formulaire', 'compact' => 'Compacte'];
    const API = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    const LOG_DAYS = 90;

    /** @var array|null vérification de la requête en cours : Cloudflare refuse un jeton vérifié deux fois */
    private $check;
    /** @var string|null refus prononcé avant que le contrôleur n'existe (actionDispatcher), affiché par setMedia */
    private $pending;

    public function __construct()
    {
        $this->name = 'cfturnstile';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'ZM40';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'Cloudflare Turnstile';
        $this->description = 'Vérification anti-robot Cloudflare Turnstile sur la connexion, la création de compte, le mot de passe oublié, le contact, la newsletter et le back-office.';
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
    }

    /** thirty bees et PS 1.6 : structure 1.6, tout passe par actionDispatcher. */
    public static function legacy()
    {
        return version_compare(_PS_VERSION_, '1.7', '<');
    }

    public function install()
    {
        $defaults = [
            'CFTURNSTILE_SITEKEY' => '', 'CFTURNSTILE_SECRET' => '', 'CFTURNSTILE_VERIFIED' => '',
            'CFTURNSTILE_FORMS' => json_encode(array_keys(self::forms())), 'CFTURNSTILE_SELECTORS' => '{}',
            'CFTURNSTILE_THEME' => 'auto', 'CFTURNSTILE_SIZE' => 'normal', 'CFTURNSTILE_SKIP_LOGGED' => 1,
            'ZM40_NET_ENABLED' => 1, // réglage commun aux modules ZM40 : jamais supprimé à la désinstallation
        ];
        foreach ($defaults as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }
        return parent::install()
            && Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'cfturnstile_log` (
                    `id_cfturnstile_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `date_add` DATETIME NOT NULL,
                    `form` VARCHAR(16) NOT NULL,
                    `blocked` TINYINT(1) UNSIGNED NOT NULL,
                    `ip` VARCHAR(45) NOT NULL,
                    `codes` VARCHAR(255) NOT NULL,
                    PRIMARY KEY (`id_cfturnstile_log`),
                    KEY `date_add` (`date_add`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4')
            && $this->registerHook(self::legacy() ? self::HOOKS_TB : self::HOOKS_PS);
    }

    public function uninstall()
    {
        foreach (['SITEKEY', 'SECRET', 'VERIFIED', 'FORMS', 'SELECTORS', 'THEME', 'SIZE', 'SKIP_LOGGED'] as $key) {
            Configuration::deleteByName('CFTURNSTILE_'.$key);
        }
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'cfturnstile_log`');
        return parent::uninstall();
    }

    /* ---------- Réglages ---------- */

    /** @return array formulaires de la plateforme */
    public static function forms()
    {
        if (self::legacy()) {
            return self::FORMS_TB;
        }
        $forms = self::FORMS_PS;
        $forms['admin'][2] = version_compare(_PS_VERSION_, '9.0.0', '<') ? self::ADMIN_PS8 : self::ADMIN_PS9;
        return $forms;
    }

    /** Les clés enregistrées sont celles qui ont réussi un essai : sinon la protection reste inactive. */
    public static function keysVerified()
    {
        $site = (string) Configuration::get('CFTURNSTILE_SITEKEY');
        $secret = (string) Configuration::get('CFTURNSTILE_SECRET');
        return $site !== '' && $secret !== '' && hash_equals(self::keysHash($site, $secret), (string) Configuration::get('CFTURNSTILE_VERIFIED'));
    }

    public static function keysHash($site, $secret)
    {
        return hash('sha256', $site.'|'.$secret._COOKIE_KEY_);
    }

    /** @return string[] formulaires protégés */
    public static function enabledForms()
    {
        $forms = json_decode((string) Configuration::get('CFTURNSTILE_FORMS'), true);
        return is_array($forms) ? array_values(array_intersect(array_keys(self::forms()), $forms)) : [];
    }

    /** Formulaire protégé : clés vérifiées et formulaire coché. */
    public static function guards($form)
    {
        return self::keysVerified() && in_array($form, self::enabledForms(), true);
    }

    public static function selector($form)
    {
        $custom = json_decode((string) Configuration::get('CFTURNSTILE_SELECTORS'), true);
        $forms = self::forms();
        return isset($custom[$form]) && trim($custom[$form]) !== '' ? trim($custom[$form]) : $forms[$form][2];
    }

    private function customerLogged()
    {
        return $this->context->customer && $this->context->customer->isLogged();
    }

    /* ---------- Contrôle ---------- */

    /**
     * Le jeton envoyé est-il accepté ? Cloudflare injoignable : oui (noté au journal). Client connecté dispensé
     * (réglage) : oui, sauf pour le back-office.
     */
    public function passes($form)
    {
        if ($form !== 'admin' && Configuration::get('CFTURNSTILE_SKIP_LOGGED') && $this->customerLogged()) {
            return true;
        }
        if ($this->check === null) {
            $this->check = CfTurnstileVerifier::verify((string) Configuration::get('CFTURNSTILE_SECRET'),
                (string) Tools::getValue('cf-turnstile-response'), (string) Tools::getRemoteAddr());
            if (!$this->check['ok'] || $this->check['open']) {
                $this->log($form, !$this->check['ok'], $this->check['codes']);
            }
        }
        return $this->check['ok'];
    }

    public function message()
    {
        return $this->l('La vérification anti-robot n\'a pas abouti. Patientez quelques secondes que la case soit validée, puis renvoyez le formulaire.');
    }

    /** @return string[] champs déclencheurs présents dans la requête */
    private static function sent(array $fields)
    {
        return array_values(array_filter($fields, function ($field) {
            // Mot de passe oublié : seul l'envoi du formulaire compte (le lien reçu par e-mail arrive en GET).
            return isset($_POST[$field]) || ($field !== 'email' && isset($_GET[$field]));
        }));
    }

    /**
     * PS 1.7 / 8 : formulaires dont la version installée n'a pas encore le hook natif => contrôleurs qui les traitent.
     * Ils sont contrôlés par actionDispatcher, comme sous thirty bees.
     */
    public static function dispatched()
    {
        $list = [];
        if (version_compare(_PS_VERSION_, '1.7.7', '<')) { // actionFrontControllerInitBefore, actionAdminLoginController*Before
            $list = ['login' => ['AuthController', 'OrderController'], 'password' => ['PasswordController'],
                'contact' => ['ContactController'], 'admin' => ['AdminLoginController']];
        }
        if (version_compare(_PS_VERSION_, '8.2.1', '<')) { // actionSubmitAccountBefore : commande à partir de 8.2.1
            $list['create'] = ['AuthController', 'OrderController'];
        }
        return $list;
    }

    /** Formulaire protégé que cette requête envoie, avant que le contrôleur ne le traite (thirty bees, PS 1.7 / 8 : voir dispatched()). */
    public function hookActionDispatcher($params)
    {
        $class = isset($params['controller_class']) ? (string) $params['controller_class'] : '';
        if ($class === '' || !self::keysVerified()) {
            return;
        }
        $dispatched = self::legacy() ? [] : self::dispatched();
        foreach (self::enabledForms() as $form) {
            if (self::legacy()) {
                list($controller, $fields) = self::FORMS_TB[$form];
                $other = $controller === '' ? strpos($class, 'Admin') === 0 : $class !== $controller;
            } else {
                $fields = self::FORMS_PS[$form][1];
                $other = !isset($dispatched[$form]) || !in_array($class, $dispatched[$form], true);
            }
            if ($other) {
                continue;
            }
            $sent = self::sent($fields);
            if (!$sent) {
                continue;
            }
            if (!$this->passes($form)) {
                $this->refuse($form, $sent, isset($this->context->controller) ? $this->context->controller : null);
            }
            return;
        }
    }

    /** PS 1.7.7+ / 8 / 9 : connexion client, mot de passe oublié, contact, qui n'ont pas de hook avant traitement. */
    public function hookActionFrontControllerInitBefore($params)
    {
        $controller = isset($params['controller']) ? $params['controller'] : null;
        if (!$controller || empty($controller->php_self) || !self::keysVerified()) {
            return;
        }
        $forms = self::forms();
        foreach (array_intersect(['login', 'password', 'contact'], self::enabledForms()) as $form) {
            if (!in_array($controller->php_self, $forms[$form][0], true) || !($sent = self::sent($forms[$form][1]))) {
                continue;
            }
            if (!$this->passes($form)) {
                $this->refuse($form, $sent, $controller);
            }
            return;
        }
    }

    /** PS 1.7 / 8 / 9 : création de compte (page d'inscription ; commande, y compris en invité, à partir de 8.2.1). false : compte non créé. */
    public function hookActionSubmitAccountBefore()
    {
        if (!self::guards('create') || !$this->onPage('create') || $this->passes('create')) {
            return true;
        }
        $this->context->controller->errors[] = $this->message();
        return false;
    }

    /** PS 1.7 / 8 / 9 : inscription à la newsletter (ps_emailsubscription) ; la désinscription (action 1) n'est pas contrôlée. */
    public function hookActionNewsletterRegistrationBefore($params)
    {
        if (self::guards('newsletter') && (string) (isset($params['action']) ? $params['action'] : '') !== '1' && !$this->passes('newsletter')) {
            $params['hookError'] = $this->message(); // passé par référence
        }
    }

    /** PS 8 : connexion au back-office ; une erreur ajoutée ici arrête la connexion. */
    public function hookActionAdminLoginControllerLoginBefore($params)
    {
        if (self::guards('admin') && !$this->passes('admin')) {
            $params['controller']->errors[] = $this->message();
        }
    }

    /** PS 8 : mot de passe oublié du back-office ; ici une erreur n'arrête pas l'envoi, on répond tout de suite. */
    public function hookActionAdminLoginControllerForgotBefore()
    {
        if (self::guards('admin') && !$this->passes('admin')) {
            $this->refuse('admin', []);
        }
    }

    /** PS 9 : mot de passe oublié du back-office (formulaire Symfony) : un champ dont la contrainte vérifie le jeton. */
    public function hookActionEmployeeRequestPasswordResetForm($params)
    {
        if (!self::guards('admin') || !isset($params['form_builder'])) {
            return;
        }
        $module = $this;
        $params['form_builder']->add('cf_turnstile', 'Symfony\Component\Form\Extension\Core\Type\HiddenType', [
            'mapped' => false,
            'constraints' => [new Symfony\Component\Validator\Constraints\Callback(function ($value, $context) use ($module) {
                if (!$module->passes('admin')) {
                    $context->addViolation($module->message());
                }
            })],
        ]);
    }

    /** PS 9 : connexion au back-office, appelée par CfTurnstileAdminLoginListener. @return string|null erreur */
    public function adminLoginError()
    {
        return self::guards('admin') && !$this->passes('admin') ? $this->message() : null;
    }

    private function onPage($form)
    {
        $forms = self::forms();
        $controller = $this->context->controller;
        return isset($controller->php_self) && in_array($controller->php_self, $forms[$form][0], true);
    }

    /** Refus : réponse au format attendu par la page (ajax), sinon formulaire réaffiché avec le message. */
    private function refuse($form, array $sent, $controller = null)
    {
        $message = $this->message();
        if ($form === 'admin') {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['hasErrors' => true, 'errors' => [$message]]));
        }
        if (Tools::getValue('ajax')) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['hasError' => true, 'errors' => [$message]]));
        }
        foreach ($sent as $field) {
            unset($_POST[$field], $_GET[$field], $_REQUEST[$field]);
        }
        if ($form === 'newsletter') {
            // thirty bees, blocknewsletter : message dans son bloc ; la page, quelconque, n'affiche pas forcément les erreurs.
            $this->context->smarty->assign(['msg' => $message, 'nw_error' => true]);
        } elseif ($controller) {
            $controller->errors[] = $message;
        } else {
            $this->pending = $message; // PS 1.7 / 8 : le contrôleur n'existe pas encore, voir hookActionFrontControllerSetMedia
        }
    }

    private function log($form, $blocked, array $codes)
    {
        $db = Db::getInstance();
        $db->insert('cfturnstile_log', [
            'date_add' => date('Y-m-d H:i:s'), 'form' => pSQL($form), 'blocked' => (int) $blocked,
            'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'codes' => pSQL(Tools::substr(implode(', ', $codes), 0, 255)),
        ]);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'cfturnstile_log` WHERE `date_add` < \''.pSQL(date('Y-m-d H:i:s', time() - self::LOG_DAYS * 86400)).'\'');
    }

    /* ---------- Widget ---------- */

    private function widgetConfig(array $forms)
    {
        $selectors = [];
        $triggers = [];
        $all = self::forms();
        foreach ($forms as $form) {
            $selectors[$form] = self::selector($form);
            $triggers[$form] = $all[$form][1];
        }
        return [
            'sitekey' => (string) Configuration::get('CFTURNSTILE_SITEKEY'), 'theme' => (string) Configuration::get('CFTURNSTILE_THEME'),
            'size' => (string) Configuration::get('CFTURNSTILE_SIZE'), 'language' => (string) $this->context->language->iso_code,
            'api' => self::API.'?render=explicit&onload=cfTurnstileOnload', 'selectors' => $selectors, 'triggers' => $triggers,
        ];
    }

    /** @return string[] formulaires de la boutique à équiper sur cette page */
    private function shopForms()
    {
        if (!self::keysVerified() || (Configuration::get('CFTURNSTILE_SKIP_LOGGED') && $this->customerLogged())) {
            return [];
        }
        return array_values(array_diff(self::enabledForms(), ['admin']));
    }

    /** thirty bees */
    public function hookDisplayHeader()
    {
        if (self::legacy() && ($forms = $this->shopForms())) {
            Media::addJsDef(['cfTurnstile' => $this->widgetConfig($forms)]);
            $this->context->controller->addJS($this->_path.'views/js/turnstile.js');
            $this->context->controller->addCSS($this->_path.'views/css/turnstile.css');
        }
        return '';
    }

    /** PS 1.7 / 8 / 9 */
    public function hookActionFrontControllerSetMedia()
    {
        if ($this->pending) { // setMedia passe avant postProcess : le message s'affiche avec le formulaire
            $this->context->controller->errors[] = $this->pending;
            $this->pending = null;
        }
        if (!($forms = $this->shopForms())) {
            return;
        }
        Media::addJsDef(['cfTurnstile' => $this->widgetConfig($forms)]);
        $this->context->controller->registerJavascript('cfturnstile', 'modules/'.$this->name.'/views/js/turnstile.js', ['position' => 'bottom', 'priority' => 200]);
        $this->context->controller->registerStylesheet('cfturnstile', 'modules/'.$this->name.'/views/css/turnstile.css');
    }

    /** PS 1.7 / 8 / 9 : emplacement du widget dans les formulaires qui ont un hook d'affichage. */
    private function slot($form)
    {
        return in_array($form, $this->shopForms(), true) ? '<div class="cf-turnstile-box" data-cf-form="'.$form.'"></div>' : '';
    }

    /** Formulaire client : uniquement là où il crée un compte (pas la page « Mes informations »). */
    public function hookDisplayCustomerAccountForm()
    {
        return $this->onPage('create') ? $this->slot('create') : '';
    }

    public function hookDisplayNewsletterRegistration()
    {
        return $this->slot('newsletter');
    }

    /** Connexion au back-office (thirty bees, PS 8) : pas de variables JS, la configuration passe par l'adresse du script. */
    public function hookActionAdminLoginControllerSetMedia()
    {
        if (!self::guards('admin')) {
            return;
        }
        $this->context->controller->addJS($this->_path.'views/js/turnstile.js?'.http_build_query(['cfg' => json_encode($this->widgetConfig(['admin']))]));
        $this->context->controller->addCSS($this->_path.'views/css/turnstile.css');
    }

    /** Connexion au back-office (PS 9, page Symfony) : configuration et script posés par le hook de la page. */
    public function hookDisplayAdminLogin()
    {
        if (!self::guards('admin')) {
            return '';
        }
        return '<script>window.cfTurnstile = '.json_encode($this->widgetConfig(['admin']), JSON_HEX_TAG | JSON_HEX_AMP).';</script>'
            .'<link rel="stylesheet" href="'.$this->_path.'views/css/turnstile.css?v='.$this->version.'">'
            .'<script src="'.$this->_path.'views/js/turnstile.js?v='.$this->version.'"></script>';
    }

    /* ---------- Back-office ---------- */

    public function getContent()
    {
        $notice = '';
        if (Tools::isSubmit('submitCfTurnstile')) {
            $notice = $this->saveSettings();
        }
        $this->context->controller->addCSS($this->_path.'views/css/zm40-common.css');
        $this->context->controller->addCSS($this->_path.'views/css/cfturnstile-admin.css');
        $db = Db::getInstance();
        $since = date('Y-m-d H:i:s', time() - 30 * 86400);
        $this->context->smarty->assign([
            'cft' => [
                'version' => $this->version, 'action' => $this->context->link->getAdminLink('AdminModules').'&configure='.$this->name,
                'notice' => $notice, 'sitekey' => (string) Configuration::get('CFTURNSTILE_SITEKEY'),
                'hasSecret' => (string) Configuration::get('CFTURNSTILE_SECRET') !== '', 'verified' => self::keysVerified(),
                'theme' => (string) Configuration::get('CFTURNSTILE_THEME'), 'size' => (string) Configuration::get('CFTURNSTILE_SIZE'),
                'themes' => self::THEMES, 'sizes' => self::SIZES, 'skipLogged' => (bool) Configuration::get('CFTURNSTILE_SKIP_LOGGED'), 'enabled' => self::enabledForms(),
                'labels' => self::LABELS, 'defaults' => array_map(function ($f) { return $f[2]; }, self::forms()),
                'selectors' => json_decode((string) Configuration::get('CFTURNSTILE_SELECTORS'), true) ?: [],
                'recaptcha' => Module::isEnabled('nocaptcharecaptcha'), 'api' => self::API,
                'stats' => $db->executeS('SELECT `form`, SUM(`blocked`) AS blocked, SUM(1 - `blocked`) AS open FROM `'._DB_PREFIX_.'cfturnstile_log`
                    WHERE `date_add` >= \''.pSQL($since).'\' GROUP BY `form`') ?: [],
                'log' => $db->executeS('SELECT * FROM `'._DB_PREFIX_.'cfturnstile_log` ORDER BY `id_cfturnstile_log` DESC LIMIT 50') ?: [],
                // chemin complet : PS 1.6 et thirty bees n'ont pas la ressource Smarty « module: »
                'partials' => _PS_MODULE_DIR_.$this->name.'/views/templates/admin/_partials/',
            ],
            'zm40_net_enabled' => Zm40CommonCft::isNetEnabled() ? 1 : 0,
            'zm40_footer_html' => Zm40CommonCft::footer('Cloudflare Turnstile', $this->version, 'cfturnstile'),
            'zm40_update' => Zm40CommonCft::checkUpdate('cfturnstile', $this->version),
            'zm40_modules' => Zm40CommonCft::modulesFeed('cfturnstile'),
            'zm40_about_name' => 'Cloudflare Turnstile',
            'zm40_about_license' => 'OSL 3.0',
            'zm40_about_github' => Zm40CommonCft::githubUrl('cfturnstile'),
            'zm40_about_site' => Zm40CommonCft::siteUrl('cfturnstile', 'panel', '/contact'),
            'zm40_about_modules' => Zm40CommonCft::siteUrl('cfturnstile', 'panel', '/'),
        ]);
        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /** Enregistre ; les clés ne sont marquées vérifiées que si le widget de la page a obtenu un jeton accepté par Cloudflare. */
    private function saveSettings()
    {
        $site = trim((string) Tools::getValue('sitekey'));
        $secret = trim((string) Tools::getValue('secret'));
        if ($secret === '') {
            $secret = (string) Configuration::get('CFTURNSTILE_SECRET'); // champ laissé vide : clé secrète inchangée
        }
        $changed = $site !== (string) Configuration::get('CFTURNSTILE_SITEKEY') || $secret !== (string) Configuration::get('CFTURNSTILE_SECRET');
        Configuration::updateValue('CFTURNSTILE_SITEKEY', $site);
        Configuration::updateValue('CFTURNSTILE_SECRET', $secret);
        Configuration::updateValue('CFTURNSTILE_THEME', isset(self::THEMES[(string) Tools::getValue('theme')]) ? Tools::getValue('theme') : 'auto');
        Configuration::updateValue('CFTURNSTILE_SIZE', isset(self::SIZES[(string) Tools::getValue('size')]) ? Tools::getValue('size') : 'normal');
        Configuration::updateValue('CFTURNSTILE_SKIP_LOGGED', (int) (bool) Tools::getValue('skip_logged'));
        if ((int) (bool) Tools::getValue('zm40_net') !== (Zm40CommonCft::isNetEnabled() ? 1 : 0)) {
            Configuration::updateValue('ZM40_NET_ENABLED', (int) (bool) Tools::getValue('zm40_net'));
            Zm40CommonCft::clearFeedCache();
        }
        $all = self::forms();
        $forms = array_values(array_intersect(array_keys($all), (array) Tools::getValue('forms', [])));
        Configuration::updateValue('CFTURNSTILE_FORMS', json_encode($forms));
        $selectors = [];
        foreach (array_keys($all) as $form) {
            $value = trim(strip_tags((string) Tools::getValue('selector_'.$form)));
            if ($value !== '' && $value !== $all[$form][2]) {
                $selectors[$form] = $value;
            }
        }
        Configuration::updateValue('CFTURNSTILE_SELECTORS', json_encode($selectors));

        if ($site === '' || $secret === '') {
            Configuration::updateValue('CFTURNSTILE_VERIFIED', '');
            return 'Enregistré. Renseignez les deux clés pour activer la protection.';
        }
        if ($changed) {
            // Le widget de la page utilisait l'ancienne clé de site : nouvel essai nécessaire.
            Configuration::updateValue('CFTURNSTILE_VERIFIED', '');
            return 'Clés enregistrées. Attendez que la case de vérification ci-dessous soit validée, puis enregistrez encore une fois pour activer la protection.';
        }
        $token = (string) Tools::getValue('cf-turnstile-response');
        if ($token === '') {
            return self::keysVerified() ? 'Enregistré.' : 'Enregistré. La case de vérification n\'était pas encore validée : la protection reste inactive.';
        }
        $check = CfTurnstileVerifier::verify($secret, $token, (string) Tools::getRemoteAddr(), true);
        if ($check['ok'] && !$check['open']) {
            Configuration::updateValue('CFTURNSTILE_VERIFIED', self::keysHash($site, $secret));
            return 'Enregistré. Clés vérifiées auprès de Cloudflare : la protection est active.';
        }
        Configuration::updateValue('CFTURNSTILE_VERIFIED', '');
        return 'Cloudflare refuse l\'essai ('.implode(', ', $check['codes']).') : vérifiez les clés. La protection reste inactive.';
    }
}
