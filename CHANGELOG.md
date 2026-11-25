# Cloudflare Turnstile (cfturnstile)

## 1.0.0 - 25/11/2026

Première version publique, libre et open source (OSL 3.0).

- thirty bees, PrestaShop 1.6, 1.7, 8 et 9, sans override
- formulaires protégés : connexion client (page de connexion et commande), création de compte (page d'inscription et commande, y compris la commande en invité), mot de passe oublié, contact, inscription à la newsletter, connexion et mot de passe oublié du back-office
- PrestaShop 1.7, 8 et 9 : hooks natifs de chaque formulaire
  - création de compte : `displayCustomerAccountForm` et `actionSubmitAccountBefore`
  - newsletter (ps_emailsubscription) : `displayNewsletterRegistration` et `actionNewsletterRegistrationBefore` ; la désinscription n'est pas contrôlée
  - connexion client, mot de passe oublié, contact : `actionFrontControllerInitBefore`
  - back-office PS 1.7.7 à 8 : `actionAdminLoginControllerLoginBefore` et `actionAdminLoginControllerForgotBefore`
  - back-office PS 9 (page Symfony) : `CheckPassportEvent` pour la connexion (la reconnexion « Rester connecté » n'est pas contrôlée) et une contrainte sur le formulaire de mot de passe oublié (`actionEmployeeRequestPasswordResetForm`)
- là où la version installée n'a pas encore le hook natif, contrôle par `actionDispatcher` avant le traitement du formulaire : connexion client, mot de passe oublié, contact et back-office avant PS 1.7.7 ; création de compte sous PS 1.7 et commande en invité avant PS 8.2.1
- thirty bees et PS 1.6 : contrôle par `actionDispatcher` (newsletter : blocknewsletter)
- widget dans l'emplacement prévu par le thème, sinon devant le bouton d'envoi (sélecteur CSS modifiable par formulaire) ; un seul widget par formulaire ; formulaires ajoutés après le chargement de la page équipés aussi ; widget de la newsletter renouvelé après chaque envoi en ajax
- protection active seulement après un essai réussi des clés ; Cloudflare injoignable : le formulaire passe et l'événement est noté au journal (90 jours)
- page de configuration : réglages, formulaires, journal, onglet Modules ZM40 (liste des modules et vérification des mises à jour, désactivables), pied de page commun avec la charte pour une cybersécurité responsable
