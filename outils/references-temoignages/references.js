
/* Lead Performant — Références et témoignages sur l'accueil et les pages des pôles (20/11/2026).
   Lit /references/flux.json, réécrit à chaque modification dans le tableau de bord ; la section reste masquée si rien n'est publié. */
(function () {
  "use strict";
  var boxes = Array.prototype.slice.call(document.querySelectorAll("[data-lp-refs]"));
  if (!boxes.length || !window.fetch) return;
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); };
  var MOIS = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
  function taille(w, h) {
    if (!(w > 0 && h > 0)) return [120, 48];
    var r = w / h, dw = Math.sqrt(4200 * r), dh = dw / r;
    if (dw > 168) { dw = 168; dh = dw / r; }
    if (dh > 60) { dh = 60; dw = dh * r; }
    return [Math.round(dw), Math.round(dh)];
  }
  function logo(l) {
    var t = taille(l.w, l.h);
    var img = '<img src="' + esc(l.src) + '" alt="' + esc(l.nom) + '" width="' + t[0] + '" height="' + t[1] + '" loading="lazy" decoding="async">';
    var safe = /^https?:\/\//i.test(l.lien || "");
    return '<li' + (l.sombre ? ' class="lpr-logo-sombre"' : "") + ">" + (safe ? '<a href="' + esc(l.lien) + '" target="_blank" rel="noopener nofollow" title="' + esc(l.nom) + '">' + img + "</a>" : img) + "</li>";
  }
  function tem(t) {
    var paras = String(t.texte || "").split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean).map(function (p) { return "<p>" + esc(p) + "</p>"; }).join("");
    var date = /^\d{4}-\d{2}$/.test(t.date || "") ? MOIS[parseInt(t.date.slice(5), 10) - 1] + " " + t.date.slice(0, 4) : "";
    var ctx = [t.contexte, date].filter(Boolean).join(" · ");
    var pole = t.pole === "p1" ? '<span class="lpr-pole" title="Art oratoire et leadership">I</span>' : t.pole === "p2" ? '<span class="lpr-pole" title="Médiation et gestion des conflits">II</span>' : "";
    return '<figure class="lpr-tem"><span class="lpr-q" aria-hidden="true">«</span><blockquote>' + paras + "</blockquote><figcaption>"
      + (t.auteur ? "<strong>" + esc(t.auteur) + "</strong>" : "") + (t.qualite ? "<span>" + esc(t.qualite) + "</span>" : "")
      + (ctx || pole ? "<em>" + pole + esc(ctx) + "</em>" : "") + "</figcaption></figure>";
  }
  fetch("/references/flux.json", { cache: "no-cache", headers: { Accept: "application/json" } })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      boxes.forEach(function (sec) {
        var pole = sec.getAttribute("data-lp-refs") || "tous";
        var ok = function (x) { return pole === "tous" || x.pole === "tous" || x.pole === pole; };
        var L = (d.logos || []).filter(ok).slice(0, 12);
        var T = (d.temoignages || []).filter(ok).filter(function (t) { return String(t.texte || "").length <= 700; }).slice(0, 3);
        if (!L.length && !T.length) return;
        var ul = sec.querySelector("[data-lp-logos]"), box = sec.querySelector("[data-lp-tems]");
        if (ul && L.length) { ul.innerHTML = L.map(logo).join(""); ul.hidden = false; }
        if (box && T.length) { box.innerHTML = T.map(tem).join(""); box.className += " lpr-n" + T.length; box.hidden = false; }
        sec.hidden = false;
      });
    })
    .catch(function () { /* section laissée masquée */ });
})();
