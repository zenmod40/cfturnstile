/**
 * Cloudflare Turnstile : un widget dans chaque formulaire protégé, soit dans l'emplacement posé par un hook du thème
 * (.cf-turnstile-box[data-cf-form], PrestaShop 8 / 9), soit devant le bouton trouvé par le sélecteur du formulaire.
 * Configuration : variable cfTurnstile (boutique, BO PS 9) ou paramètre « cfg » de l'adresse de ce script
 * (connexion au back-office thirty bees / PS 8).
 * L'API de Cloudflare n'est chargée que sur les pages qui ont un formulaire protégé.
 */
(function () {
  'use strict';
  // Script parfois inclus deux fois (page de connexion au back-office) : une seule exécution, sinon deux ajouts du jeton.
  if (window.cfTurnstileStarted) { return; }
  window.cfTurnstileStarted = true;
  var cfg = window.cfTurnstile;
  if (!cfg) {
    var tags = document.getElementsByTagName('script');
    for (var i = 0; i < tags.length && !cfg; i++) {
      var m = /cfturnstile\/views\/js\/turnstile\.js\?cfg=([^&]+)/.exec(tags[i].src || '');
      if (m) { try { cfg = JSON.parse(decodeURIComponent(m[1].replace(/\+/g, ' '))); } catch (e) {} }
    }
  }
  if (!cfg || !cfg.selectors) { return; }
  var widgets = {}; // formulaire => [id des widgets]

  // Emplacements du formulaire encore vides : ceux des hooks, puis les boutons du sélecteur (vide : aucun).
  function targets(form) {
    var list = [];
    Array.prototype.forEach.call(document.querySelectorAll('.cf-turnstile-box[data-cf-form="' + form + '"]'), function (box) {
      if (!box.getAttribute('data-cf-turnstile')) { list.push({ box: box }); }
    });
    if (cfg.selectors[form]) {
      try {
        Array.prototype.forEach.call(document.querySelectorAll(cfg.selectors[form]), function (button) {
          if (!button.getAttribute('data-cf-turnstile')) { list.push({ button: button }); }
        });
      } catch (e) {} // sélecteur mal saisi
    }
    return list;
  }

  function render() {
    if (!window.turnstile) { return; }
    Object.keys(cfg.selectors).forEach(function (form) {
      targets(form).forEach(function (t) {
        var box = t.box;
        if (t.button) {
          t.button.setAttribute('data-cf-turnstile', '1');
          // Un widget par formulaire : certains thèmes ont deux boutons d'envoi (mobile et bureau).
          if (t.button.form && t.button.form.querySelector('.cf-turnstile-box[data-cf-form="' + form + '"]')) { return; }
          box = document.createElement('div');
          box.className = 'cf-turnstile-box';
          box.setAttribute('data-cf-form', form);
          t.button.parentNode.insertBefore(box, t.button);
        }
        box.setAttribute('data-cf-turnstile', '1');
        var id = window.turnstile.render(box, { sitekey: cfg.sitekey, theme: cfg.theme, size: cfg.size, language: cfg.language || 'auto', action: form });
        (widgets[form] = widgets[form] || []).push(id);
      });
    });
  }
  window.cfTurnstileOnload = render;

  function pending() {
    return Object.keys(cfg.selectors).some(function (form) { return targets(form).length > 0; });
  }
  var apiRequested = false;
  function check() {
    if (!pending()) { return; }
    if (window.turnstile) { render(); return; }
    if (apiRequested) { return; } // l'API appellera render() une fois chargée
    apiRequested = true;
    var s = document.createElement('script');
    s.src = cfg.api;
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
  }
  // Formulaires ajoutés après le chargement (création de compte du thème, commande en une page) : surveillés aussi.
  function start() {
    check();
    if (window.MutationObserver && document.body) {
      var timer = null;
      new MutationObserver(function () {
        if (!timer) { timer = setTimeout(function () { timer = null; check(); }, 50); }
      }).observe(document.body, { childList: true, subtree: true });
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }

  // Formulaires envoyés en ajax : si la page n'envoie que des champs choisis (connexion au back-office, commande en
  // une page), on y ajoute le jeton ; il ne sert qu'une fois, on renouvelle les widgets après la réponse (newsletter
  // de PrestaShop, qui envoie le formulaire entier). Pris au niveau de XMLHttpRequest, pas de jQuery : certaines
  // pages chargent jQuery deux fois.
  function formOf(data) {
    var found = '';
    Object.keys(cfg.triggers || {}).forEach(function (form) {
      cfg.triggers[form].forEach(function (field) {
        if (!found && new RegExp('(^|&)' + field + '=').test(data)) { found = form; }
      });
    });
    return found;
  }
  function token(form) {
    var ids = widgets[form] || [];
    for (var i = 0; i < ids.length; i++) {
      var t = '';
      try { t = window.turnstile.getResponse(ids[i]); } catch (e) {} // widget retiré de la page par le thème
      if (t) { return t; }
    }
    return '';
  }
  function reset(form) {
    Object.keys(widgets).forEach(function (f) {
      if (!form || f === form) {
        widgets[f].forEach(function (id) { try { window.turnstile.reset(id); } catch (e) {} });
      }
    });
  }
  var send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.send = function (body) {
    if (typeof body === 'string' && window.turnstile) {
      var form = formOf(body);
      var carries = body.indexOf('cf-turnstile-response=') !== -1;
      if (form && !carries) {
        body += '&cf-turnstile-response=' + encodeURIComponent(token(form));
      }
      if (form || carries) {
        this.addEventListener('loadend', function () { reset(form); });
      }
    }
    return send.call(this, body);
  };
})();
