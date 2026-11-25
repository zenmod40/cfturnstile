# Cloudflare Turnstile pour PrestaShop et thirty bees

> La vérification anti-robot de Cloudflare, le plus souvent invisible, sur les formulaires de la boutique
> et sur la connexion au back-office. Sans override, avec les hooks natifs de chaque version.

Module libre et open source (OSL 3.0) pour thirty bees, PrestaShop 1.6, 1.7, 8 et 9.

![PrestaShop 1.6 → 9](https://img.shields.io/badge/PrestaShop-1.6%20%E2%86%92%209-blue) ![thirty bees](https://img.shields.io/badge/thirty%20bees-oui-blue) [![Version](https://img.shields.io/github/v/release/zenmod40/cfturnstile)](https://github.com/zenmod40/cfturnstile/releases/latest)
![License: OSL 3.0](https://img.shields.io/badge/License-OSL--3.0-blue)

> **[Page du module sur zm40.com](https://zm40.com/cfturnstile)**

## Formulaires protégés

- Connexion client, sur la page de connexion et dans la commande.
- Création de compte, sur la page d'inscription et dans la commande, y compris la commande en invité.
- Mot de passe oublié.
- Formulaire de contact.
- Inscription à la newsletter (ps_emailsubscription, blocknewsletter sous thirty bees). La désinscription n'est pas contrôlée.
- Connexion et mot de passe oublié du back-office.

Chaque formulaire se coche ou se décoche dans la configuration.

## Comment le module s'accroche

Un seul module, un seul code. La version de la boutique décide du chemin :

- **PrestaShop 1.7, 8 et 9** : les hooks natifs de chaque formulaire (`actionSubmitAccountBefore`,
  `actionNewsletterRegistrationBefore`, `actionAdminLoginControllerLoginBefore`…) et
  `actionFrontControllerInitBefore` pour ceux qui n'en ont pas.
- **PrestaShop 9, back-office** : la connexion passe par le pare-feu Symfony, avant tout contrôleur et tout
  hook. Un écouteur `CheckPassportEvent` y vérifie le jeton. Le mot de passe oublié reçoit une contrainte sur
  son formulaire.
- **Hook natif absent de la version installée** (PrestaShop 1.7.0 à 1.7.6, commande en invité avant 8.2.1) :
  contrôle par `actionDispatcher`, avant que le contrôleur ne traite le formulaire.
- **thirty bees et PrestaShop 1.6** : tout passe par `actionDispatcher`.

Le widget se place dans l'emplacement prévu par le thème quand il existe (`displayCustomerAccountForm`,
`displayNewsletterRegistration`, `displayAdminLogin`), sinon devant le bouton d'envoi trouvé par un sélecteur
CSS, modifiable formulaire par formulaire si votre thème diffère. Un seul widget par formulaire, même quand le
thème a deux boutons d'envoi. Les formulaires ajoutés après le chargement de la page reçoivent aussi le leur,
et le widget de la newsletter se renouvelle après chaque envoi en ajax.

L'API de Cloudflare n'est chargée que sur les pages qui ont un formulaire protégé.

## Sécurité de fonctionnement

- Tant que les clés n'ont pas réussi un essai depuis la page de configuration, rien n'est contrôlé : une clé
  fausse ne peut pas vous fermer la porte du back-office.
- Si Cloudflare est injoignable ou répond de façon illisible, le formulaire passe et l'événement est noté au
  journal. Une panne chez Cloudflare ne ferme pas la boutique.
- Un client déjà connecté peut être dispensé de vérification (réglage), sauf pour le back-office.
- Le journal garde les refus et les passages sans réponse fiable pendant 90 jours.

## Compatibilité

thirty bees, PrestaShop 1.6, 1.7, 8 et 9. Aucune dépendance Composer.

Testé sur PrestaShop 1.7.8.11 (PHP 7.4), 8.2.8 (classic) et 9.2.0 (Hummingbird). La branche thirty bees reprend
un code déjà en production sur des boutiques thirty bees.

Limites connues :

- Un tunnel de commande en une page fourni par un module tiers crée le client par ses propres appels : il n'est
  pas protégé, sauf s'il déclenche les hooks natifs de PrestaShop.
- PrestaShop 9 : la reconnexion automatique « Rester connecté » du back-office n'est pas contrôlée (pas de mot
  de passe saisi).
- Newsletter sous PrestaShop 1.7 : protégée si ps_emailsubscription est assez récent pour avoir le hook
  `actionNewsletterRegistrationBefore` (c'est le cas de la version livrée avec la 1.7.8).

## Installation

1. Télécharger la dernière release (`cfturnstile_vX.Y.Z.zip`).
2. Back-office, Modules, Téléverser un module.
3. Dans le tableau de bord Cloudflare, Turnstile : choisir « Ajouter manuellement » (pas « Configurer
   avec Spin » : l'intégration est le travail du module). Noms d'hôte : le domaine de la boutique, et sa
   version www si elle est utilisée. Mode « Managed ». Pré-autorisation : laisser « Non ». Copier la clé
   de site et la clé secrète dans la configuration du module.
4. Attendre que la case d'essai soit validée, puis enregistrer : la protection s'active.

Si le module Google reCAPTCHA (nocaptcharecaptcha) est installé, la page de configuration vous le signale :
désinstallez-le une fois Turnstile actif, pour ne pas imposer deux vérifications.

## Confidentialité

- Sur les pages qui ont un formulaire protégé, le navigateur du visiteur charge le widget depuis
  `challenges.cloudflare.com`. À l'envoi, la boutique transmet à Cloudflare le jeton et l'adresse IP du
  visiteur pour vérification. Pensez à mentionner Cloudflare Turnstile dans votre politique de confidentialité.
- Le journal enregistre la date, le formulaire, le résultat, l'adresse IP et les codes renvoyés par
  Cloudflare, pendant 90 jours.
- La page de configuration vérifie au plus une fois par jour la dernière version publiée sur GitHub et récupère
  la liste des autres modules ZM40 depuis zm40.com. Ces requêtes sont anonymes : aucune donnée de la boutique
  n'est transmise. Vous pouvez les désactiver dans l'onglet Modules ZM40.

## Support et services

Le module est offert à la communauté, sans support garanti. Les issues GitHub sont les bienvenues pour les bugs
et les idées. Voir [SUPPORT.md](SUPPORT.md).

Besoin d'aide à l'installation, d'une adaptation à votre thème ou d'une intégration à votre tunnel de commande ?
Voir [zm40.com](https://zm40.com).

## Contribuer

Les pull requests sont bienvenues. Merci de garder le style du code et d'ouvrir une issue avant les gros
changements.

## Licence

OSL 3.0 — 2026 Nicolas Michaud — ZM40 / Magic Garden — [zm40.com](https://zm40.com)

Voir [LICENSE](LICENSE) pour le texte complet.
