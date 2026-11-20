/* Lead Performant — maison mère à deux pôles (novembre 2026) : en-tête et pied de page communs,
   menu, vidéos lues à la demande, filtres, formulaire à deux pôles, Boussole relationnelle,
   contenus publiés depuis l'espace de gestion. Placé avant les interactions communes. */
(function () {
  "use strict";
  var LP = window.LP || {};
  var HEADER = __HEADER__;
  var FOOTER = __FOOTER__;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); };
  var frag = function (html) { var t = document.createElement("template"); t.innerHTML = html.trim(); return t.content.firstElementChild; };

  /* ------------------------------------------------ En-tête et pied de page des pages existantes */
  function activeKey(p) {
    if (/^\/(mediation-gestion-des-conflits)\//.test(p)) return "pole2";
    if (/^\/(art-oratoire-leadership|art-oratoire|leadership|formations|entreprises|ecoles|collectivites-institutions|calendrier|sessions|inscription)\b/.test(p) || /^\/laboratoire\.html/.test(p)) return "pole1";
    if (/^\/interventions\//.test(p)) return "interventions";
    if (/^\/ressources\//.test(p)) return "ressources";
    if (/^\/fondateur\//.test(p)) return "fondateur";
    return "";
  }
  var oldH = $("header.site-header");
  if (oldH) {
    var nh = frag(HEADER), k = activeKey(location.pathname);
    if (k) $$('[data-k="' + k + '"]', nh).forEach(function (a) { a.setAttribute("aria-current", "page"); });
    oldH.parentNode.replaceChild(nh, oldH);
    $$(".nav-backdrop").forEach(function (b) { b.parentNode.removeChild(b); });
  }
  var oldF = $("footer.site-footer");
  if (oldF) oldF.parentNode.replaceChild(frag(FOOTER), oldF);

  /* ------------------------------------------------ Menu mobile */
  var burger = $(".lp2-burger"), menu = $("#lp2-menu");
  function setMenu(open) {
    if (!burger || !menu) return;
    menu.hidden = !open; burger.setAttribute("aria-expanded", open ? "true" : "false");
    burger.setAttribute("aria-label", open ? "Fermer le menu" : "Ouvrir le menu");
    var s = $("span", burger); if (s) s.textContent = open ? "Fermer" : "Menu";
  }
  if (burger && menu) {
    burger.addEventListener("click", function () { setMenu(menu.hidden); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !menu.hidden) { setMenu(false); burger.focus(); } });
  }

  /* ------------------------------------------------ Vidéos : rien n'est chargé avant le clic */
  document.addEventListener("click", function (ev) {
    var b = ev.target.closest && ev.target.closest("[data-play]");
    if (!b) return;
    var box = b.getAttribute("data-play") ? document.getElementById(b.getAttribute("data-play")) : b.closest("[data-yt]");
    if (!box) return;
    ev.preventDefault();
    var id = box.getAttribute("data-yt"), t = box.getAttribute("data-title") || "Vidéo";
    var w = box.offsetWidth, h = box.offsetHeight;
    if (w && h) { box.style.aspectRatio = w + " / " + h; box.style.height = "auto"; }
    box.classList.add("lp2-yt");
    box.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0" title="' + esc(t) + '" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
    var fr = $("iframe", box); if (fr) fr.focus();
  });

  /* ------------------------------------------------ Filtres */
  function applyFilter(group, key) {
    var scope = document.getElementById(group.getAttribute("data-lp2-filter")) || document;
    $$("button[data-f]", group).forEach(function (b) {
      var on = b.getAttribute("data-f") === key;
      b.setAttribute("aria-pressed", on ? "true" : "false");
      b.setAttribute("style", b.getAttribute(on ? "data-on" : "data-off"));
    });
    var n = 0;
    $$("[data-item]", scope).forEach(function (it) {
      var show = key === "tout" || (" " + it.getAttribute("data-cat") + " ").indexOf(" " + key + " ") >= 0;
      it.hidden = !show; if (show && it.hasAttribute("data-count")) n++;
    });
    $$("[data-lp2-count]", scope).forEach(function (c) { c.textContent = n + (n > 1 ? " interventions" : " intervention"); });
  }
  $$("[data-lp2-filter]").forEach(function (group) {
    group.addEventListener("click", function (ev) {
      var b = ev.target.closest("button[data-f]"); if (b) applyFilter(group, b.getAttribute("data-f"));
    });
  });

  /* ------------------------------------------------ Demander un échange : deux pôles */
  var poleBox = $("[data-lp2-poles]");
  if (poleBox) {
    var BESOINS = {
      p1: [["", "Choisir"], ["echange", "Un premier échange"], ["formation", "Une formation"], ["devis", "Un devis pour mon organisation"], ["atelier", "Un atelier en établissement"], ["coaching", "Un coaching individuel"], ["intervention", "Une intervention ou une conférence"]],
      p2: [["", "Choisir"], ["boussole", "Boussole relationnelle · diagnostic"], ["desamorcage", "L'Art du Désamorçage · formation des managers"], ["alliance", "Alliance Durable · accompagnement"], ["mediation", "Demander une médiation"], ["crise", "Gérer un conflit ou une crise"], ["formation-cnv", "Organiser une formation"], ["rps", "Prévention des RPS, QVCT"], ["guide", "Recevoir le guide des 25 signaux faibles"], ["livre-blanc", "Recevoir le livre blanc"]],
      none: [["", "Choisir"], ["echange", "Un premier échange pour m'orienter"], ["intervention", "Une intervention ou une conférence"], ["autre", "Autre demande"]]
    };
    var qs = new URLSearchParams(location.search);
    var setPole = function (p, keepBesoin) {
      $$("button[data-pole]", poleBox).forEach(function (b) {
        var on = b.getAttribute("data-pole") === p;
        b.setAttribute("aria-pressed", on ? "true" : "false");
        b.setAttribute("style", b.getAttribute(on ? "data-on" : "data-off"));
      });
      var sel = $("#f-besoin"), prev = keepBesoin ? keepBesoin : "";
      if (sel) {
        sel.innerHTML = BESOINS[p].map(function (o) { return '<option value="' + o[0] + '">' + esc(o[1]) + "</option>"; }).join("");
        if (prev && $('option[value="' + prev + '"]', sel)) sel.value = prev;
      }
      var prog = $("#f-programme"), wrap = $("[data-lp2-prog]"), note = $("[data-lp2-p2note]");
      if (wrap) wrap.hidden = p !== "p1";
      if (note) note.hidden = p !== "p2";
      if (prog) { if (p === "p2") prog.value = "pole-2"; else if (p === "none") prog.value = "orientation"; else if (prog.value === "pole-2" || prog.value === "orientation") prog.value = ""; }
      poleBox.setAttribute("data-current", p);
    };
    var qp = (qs.get("pole") || "").toLowerCase(), qb = qs.get("besoin") || "";
    var start = /^(2|ii|p2|mediation|conflits)/.test(qp) ? "p2" : /^(orientation|none|autre)/.test(qp) ? "none" : "p1";
    if (!qp && qb && BESOINS.p2.some(function (o) { return o[0] === qb; }) && !BESOINS.p1.some(function (o) { return o[0] === qb; })) start = "p2";
    setPole(start, qb);
    poleBox.addEventListener("click", function (ev) {
      var b = ev.target.closest("button[data-pole]"); if (b) setPole(b.getAttribute("data-pole"), ($("#f-besoin") || {}).value);
    });
  }

  /* ------------------------------------------------ Boussole relationnelle (version courte) */
  var bx = $("[data-boussole]");
  if (bx) {
    var Q = [
      ["Communication", "Les informations importantes circulent bien entre les personnes et les services."],
      ["Communication", "Chacun peut exprimer un désaccord sans craindre d’être mis à l’écart."],
      ["Confiance", "Les engagements pris en réunion sont généralement tenus."],
      ["Confiance", "Les membres de l’équipe se font confiance pour le travail des autres."],
      ["Gestion des désaccords", "Les désaccords sont traités tôt, avant qu’ils ne s’enveniment."],
      ["Gestion des désaccords", "Lorsqu’une tension apparaît, chacun sait vers qui se tourner."],
      ["Management", "Les managers savent conduire une conversation difficile."],
      ["Management", "Les rôles et les responsabilités de chacun sont clairs."],
      ["Climat", "Les départs, absences ou plaintes restent à un niveau habituel."],
      ["Climat", "L’ambiance de travail permet de coopérer sereinement."]
    ];
    var SCALE = ["Pas du tout d’accord", "Plutôt pas d’accord", "Plutôt d’accord", "Tout à fait d’accord"];
    var NAMES = ["Communication", "Confiance", "Gestion des désaccords", "Management", "Climat"];
    var step = 0, ans = [];
    var show = function (name) {
      $$("[data-step]", bx).forEach(function (p) { p.hidden = p.getAttribute("data-step") !== name; });
      var top = bx.getBoundingClientRect().top; if (top < 0) bx.scrollIntoView({ block: "start" });
      var h = $('[data-step="' + name + '"] h1', bx); if (h) { h.setAttribute("tabindex", "-1"); h.focus({ preventScroll: true }); }
    };
    var draw = function () {
      if (step === 0) return show("intro");
      if (step >= 1 && step <= 10) {
        var q = Q[step - 1];
        $("[data-b-theme]", bx).textContent = q[0];
        $("[data-b-num]", bx).textContent = "Affirmation " + step + " sur 10";
        var bar = $("[data-b-bar]", bx); bar.style.width = (step * 10) + "%";
        var pb = $('[role="progressbar"]', bx); if (pb) pb.setAttribute("aria-valuenow", step);
        $("[data-b-q]", bx).textContent = q[1];
        $$("[data-b-opt]", bx).forEach(function (b, i) {
          b.textContent = SCALE[i];
          var on = ans[step - 1] === i;
          b.setAttribute("aria-pressed", on ? "true" : "false");
          b.setAttribute("style", b.getAttribute(on ? "data-on" : "data-off"));
        });
        return show("q");
      }
      if (step === 11) return show("gate");
      result();
    };
    var compute = function () {
      var themes = NAMES.map(function (n, i) { var s = (ans[i * 2] || 0) + (ans[i * 2 + 1] || 0); return { label: n, pct: Math.round(s / 6 * 100) }; });
      var total = ans.reduce(function (a, v) { return a + (v || 0); }, 0), score = Math.round(total / 30 * 100), level, text, next;
      if (score >= 67) { level = "Un climat plutôt apaisé, à entretenir"; text = "Les fondamentaux tiennent. Le risque principal est de laisser s’installer les petits non-dits."; next = "Outiller vos managers pour désamorcer tôt : L’Art du Désamorçage, une ou deux journées."; }
      else if (score >= 40) { level = "Une zone de vigilance"; text = "Des signaux faibles apparaissent sur plusieurs thèmes. Les traiter maintenant coûte bien moins cher qu’attendre."; next = "Objectiver la situation avec la Boussole relationnelle complète : entretiens confidentiels, cartographie, plan d’action."; }
      else { level = "Des tensions à traiter"; text = "Plusieurs thèmes sont fragilisés. Un regard extérieur et neutre est souvent nécessaire pour rétablir le dialogue."; next = "Un premier échange confidentiel pour qualifier la situation et choisir la bonne réponse : médiation, diagnostic ou accompagnement."; }
      return { themes: themes, score: score, level: level, text: text, next: next };
    };
    var sent = null;
    var result = function () {
      var r = compute();
      $("[data-b-score]", bx).textContent = r.score;
      $("[data-b-level]", bx).textContent = r.level;
      $("[data-b-leveltext]", bx).textContent = r.text;
      $("[data-b-next]", bx).textContent = r.next;
      var tpl = $("[data-b-themes]", bx);
      $$("[data-b-t]", tpl).forEach(function (li, i) {
        $("[data-b-tl]", li).textContent = r.themes[i].label;
        $("[data-b-tb]", li).style.width = r.themes[i].pct + "%";
        $("[data-b-tp]", li).textContent = r.themes[i].pct + " %";
      });
      var st = $("[data-b-sent]", bx);
      if (st) st.textContent = sent === true ? "Votre premier diagnostic · une copie vous a été adressée" : sent === false ? "Votre premier diagnostic · l’envoi de la copie n’a pas abouti, le résultat reste affiché ici" : "Votre premier diagnostic";
      show("result");
    };
    bx.addEventListener("click", function (ev) {
      var t = ev.target.closest("button"); if (!t || !bx.contains(t)) return;
      if (t.hasAttribute("data-b-start")) { step = 1; return draw(); }
      if (t.hasAttribute("data-b-opt")) { ans[step - 1] = +t.getAttribute("data-b-opt"); step++; return draw(); }
      if (t.hasAttribute("data-b-back")) { step = Math.max(step - 1, step === 1 ? 0 : 1); return draw(); }
      if (t.hasAttribute("data-b-restart")) { step = 0; ans = []; sent = null; return draw(); }
      if (t.hasAttribute("data-b-submit")) {
        var em = $("#b-email"), cs = $("#b-consent"), ee = $("#b-e-email"), ec = $("#b-e-consent");
        var okE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em.value.trim()), okC = cs.checked;
        ee.textContent = okE ? "" : "Indiquez une adresse valide, par exemple nom@domaine.fr.";
        ec.textContent = okC ? "" : "Votre accord est nécessaire pour vous adresser le diagnostic.";
        if (!okE) { em.focus(); return; } if (!okC) { cs.focus(); return; }
        var r = compute(), st = $("#b-structure"), news = $("#b-news");
        var lines = ["Boussole relationnelle · version courte", "", "Score global : " + r.score + "/100", "Niveau : " + r.level, r.text, ""];
        r.themes.forEach(function (x) { lines.push(x.label + " : " + x.pct + " %"); });
        lines.push("", "Pour aller plus loin : " + r.next, "", "Réponses (0 = pas du tout d’accord, 3 = tout à fait d’accord) :");
        Q.forEach(function (q, i) { lines.push((i + 1) + ". " + q[1] + " → " + SCALE[ans[i] || 0]); });
        lines.push("", "Ressources mensuelles : " + (news && news.checked ? "oui" : "non"));
        var fd = new FormData();
        fd.append("type", "boussole"); fd.append("email", em.value.trim()); fd.append("nom", "");
        fd.append("organisation", st && st.selectedIndex > 0 ? st.options[st.selectedIndex].text : "");
        fd.append("besoin", "Boussole relationnelle · version courte"); fd.append("message", lines.join("\n"));
        fd.append("consentement", "oui"); fd.append("site_web", ($("#b-site") || { value: "" }).value);
        t.disabled = true; var lbl = t.innerHTML; t.textContent = "Envoi en cours…";
        var done = function (ok) { sent = ok; t.disabled = false; t.innerHTML = lbl; step = 12; draw(); };
        if (LP.mode !== "prod") return done(true);
        fetch(LP.endpoint || "/contact/envoyer.php", { method: "POST", body: fd, headers: { Accept: "application/json" } })
          .then(function (res) { return res.json(); }).then(function (j) { done(!!(j && j.ok)); }).catch(function () { done(false); });
      }
    });
    draw();
  }

  /* ------------------------------------------------ Contenus publiés depuis l'espace de gestion */
  var intGrid = $("[data-lp2-int]"), artList = $("[data-lp2-arts]");
  var norm = function (s) { return String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, " ").trim(); };
  if ((intGrid || artList) && LP.api && LP.mode === "prod" && window.fetch) {
    fetch(LP.api, { headers: { Accept: "application/json" }, cache: "no-store" }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d) return;
      if (intGrid && d.interventions) {
        var known = {}; $$("[data-title]", intGrid).forEach(function (x) { known[norm(x.getAttribute("data-title"))] = 1; });
        var CAT = { television: "tv", conference: "conferences", conferences: "conferences", ateliers: "ateliers", presse: "presse", diplomatie: "diplomatie" };
        var LAB = { tv: "Télévision", conferences: "Conférences", ateliers: "Formations et ateliers", presse: "Presse", diplomatie: "Diplomatie" };
        var tpl = $("template[data-lp2-card]");
        var fresh = d.interventions.filter(function (e) { return !known[norm(e.titre)]; });
        fresh.reverse().forEach(function (e) {
          if (!tpl) return;
          var c = CAT[e.categorie] || "conferences", img = e.vignette || e.image || "";
          if (img && !/^(https?:|\/)/.test(img)) img = "/" + img;
          if (/images\.unsplash\.com/.test(img)) img = "";
          var node = tpl.content.firstElementChild.cloneNode(true);
          node.setAttribute("data-cat", c); node.setAttribute("data-title", e.titre || "");
          $("[data-c-kind]", node).textContent = (LAB[c] || "") + (e.meta ? " · " + e.meta : "");
          $("[data-c-title]", node).textContent = e.titre || "";
          $("[data-c-text]", node).textContent = e.texte || "";
          var media = $("[data-c-media]", node), act = $("[data-c-act]", node);
          if (e.youtube) {
            media.setAttribute("data-yt", e.youtube); media.setAttribute("data-title", e.titre || "Vidéo"); media.id = "v-cms-" + e.youtube;
            var im = $("img", media); if (img) { im.src = img; im.alt = ""; } else im.parentNode.removeChild(im);
            act.innerHTML = '<button type="button" data-play="' + media.id + '" style="all:unset;cursor:pointer;font-size:13.5px;font-weight:500;color:#0B1A33">Regarder <span aria-hidden="true" style="color:#7A5C1B">→</span></button>';
            var pl = $(".lp2-play", media); if (pl) pl.setAttribute("aria-label", "Lire la vidéo : " + (e.titre || ""));
          } else if (img) {
            var im2 = $("img", media); im2.src = img; im2.alt = ""; var p2 = $(".lp2-play", media); if (p2) p2.parentNode.removeChild(p2);
            var badge = $("[data-c-badge]", media); if (badge) badge.parentNode.removeChild(badge);
          } else media.parentNode.removeChild(media);
          if (!e.youtube) {
            if (e.lien) act.innerHTML = '<a href="' + esc(e.lien) + '" target="_blank" rel="noopener" style="font-size:13.5px;font-weight:500;color:#0B1A33;text-decoration:none">' + esc(e.lien_libelle || "En savoir plus") + ' <span aria-hidden="true" style="color:#7A5C1B">→</span></a>';
            else act.parentNode.removeChild(act);
          }
          intGrid.insertBefore(node, intGrid.firstChild);
        });
        var cur = $("[data-lp2-filter] button[aria-pressed='true']");
        if (cur) applyFilter(cur.closest("[data-lp2-filter]"), cur.getAttribute("data-f"));
      }
      if (artList && d.articles) {
        var url = function (a) { return a.legacy_url && a.maj === a.seed_maj ? "/" + a.legacy_url : "/ressources/article.php?slug=" + encodeURIComponent(a.slug); };
        var have = {}; $$("[data-slug]", artList).forEach(function (li) { have[li.getAttribute("data-slug")] = li; });
        var tplA = $("template[data-lp2-art]");
        d.articles.forEach(function (a) {
          var li = have[a.slug];
          if (li) { var ln = $("a", li); if (ln && a.maj !== a.seed_maj) ln.setAttribute("href", url(a)); return; }
          if (!tplA) return;
          var n = tplA.content.firstElementChild.cloneNode(true);
          var p2 = /conflit|m[ée]diation|dialogue|relation/i.test((a.categorie || "") + " " + (a.titre || ""));
          n.setAttribute("data-cat", p2 ? "p2" : "p1"); n.setAttribute("data-slug", a.slug);
          $("a", n).setAttribute("href", url(a));
          $("[data-a-kind]", n).textContent = a.categorie || "";
          $("[data-a-title]", n).textContent = a.titre || "";
          $("[data-a-desc]", n).textContent = a.resume || a.chapo || "";
          $("[data-a-time]", n).textContent = a.lecture ? a.lecture + " de lecture" : "";
          artList.insertBefore(n, artList.firstChild);
        });
        var curA = $("[data-lp2-filter] button[aria-pressed='true']");
        if (curA) applyFilter(curA.closest("[data-lp2-filter]"), curA.getAttribute("data-f"));
      }
    }).catch(function () {});
  }
})();
