/* Lead Performant — interactions communes */
(function () {
  "use strict";
  var LP = window.LP || {};
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); };
  var store = {
    get: function (k, d) { try { var v = localStorage.getItem("lp:" + k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem("lp:" + k, JSON.stringify(v)); } catch (e) {} }
  };
  var ARROW = '<span class="arrow" aria-hidden="true">→</span>';
  window.LPU = { $: $, $$: $$, esc: esc, store: store, toast: toast, copy: copy, fixImages: fixImages };

  /* ------------------------------------------------ En-tête, menu, images */
  var header = $(".site-header");
  var onScroll = function () { if (header) header.classList.toggle("scrolled", window.scrollY > 8); };
  window.addEventListener("scroll", onScroll, { passive: true }); onScroll();
  var mb = $("#menu-btn"), nav = $("#nav"), backdrop = $(".nav-backdrop"), ink = $(".nav-ink");
  var mobile = function () { return window.matchMedia("(max-width:1080px)").matches; };
  if (mb && nav) mb.addEventListener("click", function () {
    var o = nav.classList.toggle("open"); mb.setAttribute("aria-expanded", o); document.body.classList.toggle("menu-open", o);
    $("span", mb).textContent = o ? "Fermer" : "Menu";
  });
  var openItem = null, tOpen, tClose;
  function setOpen(item, on) {
    if (!item) return;
    item.classList.toggle("open", on);
    var tg = $(".mega-toggle", item); if (tg) tg.setAttribute("aria-expanded", on);
    if (!mobile() && backdrop) backdrop.classList.toggle("on", on);
    if (on) { if (openItem && openItem !== item) setOpen(openItem, false); openItem = item; } else if (openItem === item) openItem = null;
  }
  function moveInk(el) {
    if (!ink || mobile()) return;
    if (!el) { ink.classList.remove("on"); return; }
    var nr = (ink.offsetParent || nav).getBoundingClientRect(), r = el.getBoundingClientRect();
    ink.style.left = (r.left - nr.left + 11) + "px"; ink.style.width = Math.max(0, r.width - 22) + "px"; ink.classList.add("on");
  }
  $$(".nav-item", nav).forEach(function (item) {
    var link = $(".nav-link", item), mega = item.classList.contains("has-mega");
    item.addEventListener("mouseenter", function () {
      if (mobile()) return;
      moveInk(link); clearTimeout(tClose);
      if (mega) { clearTimeout(tOpen); tOpen = setTimeout(function () { setOpen(item, true); }, openItem ? 0 : 90); }
      else if (openItem) { tClose = setTimeout(function () { setOpen(openItem, false); }, 120); }
    });
    item.addEventListener("mouseleave", function () {
      if (mobile()) return;
      clearTimeout(tOpen);
      if (mega) tClose = setTimeout(function () { setOpen(item, false); }, 180);
    });
    if (mega) {
      item.addEventListener("focusin", function () { if (!mobile()) setOpen(item, true); });
      item.addEventListener("focusout", function (e) { if (!mobile() && !item.contains(e.relatedTarget)) setOpen(item, false); });
      $(".mega-toggle", item).addEventListener("click", function () { setOpen(item, !item.classList.contains("open")); });
    }
  });
  if (nav) nav.addEventListener("mouseleave", function () { moveInk(null); });
  if (backdrop) backdrop.addEventListener("click", function () { setOpen(openItem, false); });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (openItem) { var l = $(".nav-link", openItem); setOpen(openItem, false); if (l) l.focus(); }
    if (nav && nav.classList.contains("open")) mb.click();
  });
  function fixImages(root) {
    $$(".ph img, .video img", root).forEach(function (img) {
      var drop = function () { img.remove(); };
      if (img.complete && img.naturalWidth === 0) drop(); else img.addEventListener("error", drop);
    });
  }
  fixImages(document);

  function toast(msg) {
    var t = $("#toast"); if (!t) return;
    t.textContent = msg; t.classList.add("on");
    clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove("on"); }, 2600);
  }
  function copy(text) {
    var ok = function () { toast("Copié dans le presse-papiers"); };
    var fallback = function () {
      var ta = document.createElement("textarea"); ta.value = text; ta.setAttribute("readonly", ""); ta.style.position = "fixed"; ta.style.opacity = "0";
      document.body.appendChild(ta); ta.select();
      try { document.execCommand("copy"); ok(); } catch (e) { toast("Sélectionnez le texte pour le copier"); }
      ta.remove();
    };
    try { navigator.clipboard.writeText(text).then(ok, fallback); } catch (e) { fallback(); }
  }
  function contactUrl(params) {
    var q = Object.keys(params).filter(function (k) { return params[k]; }).map(function (k) { return k + "=" + encodeURIComponent(params[k]); }).join("&");
    return LP.contact + (q ? "?" + q : "");
  }
  function send(data) {
    if (LP.preview) return Promise.resolve({ preview: true });
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(LP.endpoint, { method: "POST", body: fd, headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || "envoi"); return j; }); });
  }
  var emailOk = function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); };

  /* ------------------------------------------------ Accueil : orientation */
  var orient = $("[data-orient]");
  if (orient) {
    var why = {
      assurance: "Vous voulez parler en public sans subir le trac : Éloquence Élite pose les fondations du corps, de la voix et de la structure.",
      evoluer: "Votre enjeu est professionnel : ce programme relie la parole à votre projet, du pitch à l'entretien simulé.",
      convaincre: "Vous devez faire décider : Parole d'Impact travaille vos présentations réelles, vos arguments et vos objections.",
      mobiliser: "Vous devez faire réussir une équipe : vision, délégation et feedback sont au cœur de ce programme.",
      complexe: "La situation se tend : ce programme travaille l'analyse, la décision et la négociation sous pression."
    };
    var panel = $("#orient-panel");
    $$(".obj", orient).forEach(function (b) {
      b.addEventListener("click", function () {
        $$(".obj", orient).forEach(function (x) { x.setAttribute("aria-pressed", x === b); });
        var k = b.getAttribute("data-prog"), p = LP.programmes[k], o = b.getAttribute("data-obj");
        var primary = o === "convaincre"
          ? '<a class="btn" href="' + (LP.single ? p.url + '--preparer' : p.url + '#preparer') + '">Préparer ma prochaine décision ' + ARROW + '</a>'
          : '<a class="btn" href="' + p.url + '">Découvrir le programme ' + ARROW + '</a>';
        panel.innerHTML = '<div class="ph r169"><img src="' + p.img + '" alt=""></div><div class="inner fade-in"><span class="eyebrow">Programme ' + p.n + '</span><h3>' + esc(p.title) + '</h3><p class="muted">' + esc(why[o]) + '</p><dl><dt>La question</dt><dd>' + esc(p.question) + '</dd><dt>Le résultat</dt><dd>' + esc(p.resultat) + '</dd><dt>Format</dt><dd>' + esc(p.duree) + ' · ' + esc(p.public) + '</dd></dl><div class="actions">' + primary + '<a class="link" href="' + contactUrl({ besoin: "echange", formation: k }) + '">Demander un échange</a></div></div>';
        fixImages(panel);
      });
    });
  }

  /* ------------------------------------------------ Outil générique pas à pas */
  function shell(el, opts) {
    el.innerHTML = '<div class="tool-top"><span><b>' + esc(opts.title) + '</b><span class="phase"></span></span><span class="count"></span></div><div class="bar"><i></i></div><div class="tool-body" aria-live="polite"></div><div class="tool-foot"><button type="button" class="back">← Précédent</button><span class="hint note"></span></div>';
    return { body: $(".tool-body", el), bar: $(".bar i", el), count: $(".count", el), phase: $(".phase", el), back: $(".back", el), hint: $(".hint", el), foot: $(".tool-foot", el) };
  }
  function optionsHtml(list, selected, key, multi, two) {
    return '<div class="options' + (two ? " two" : "") + '">' + list.map(function (o) {
      var v = o[0], label = o[1], sub = o[2];
      var on = multi ? (selected || []).indexOf(v) > -1 : selected === v;
      return '<button type="button" class="opt' + (multi ? " multi" : "") + '" data-k="' + key + '" data-v="' + esc(v) + '" aria-pressed="' + on + '"><span>' + esc(label) + (sub ? "<small>" + esc(sub) + "</small>" : "") + '</span></button>';
    }).join("") + "</div>";
  }

  /* ------------------------------------------------ Diagnostic d'aisance orale */
  var diagEl = $("[data-diag]");
  if (diagEl) {
    var Q = JSON.parse(diagEl.getAttribute("data-diag"));
    var OBJ = [["assurance", "Gagner en assurance à l'oral", "Parler en public sans subir le trac", "eloquence-elite"], ["evoluer", "Réussir un entretien, évoluer professionnellement", "Pitch, entretien, prise de poste", "eloquence-levier-professionnel"], ["convaincre", "Convaincre dans une situation décisive", "Comité, client, conférence, médias", "parole-d-impact"], ["mobiliser", "Mieux m'adresser à mon équipe", "Réunions, annonces, feedback", "leadership-relationnel"], ["complexe", "Tenir dans une situation tendue", "Débat, contestation, négociation", "leadership-strategique"]];
    var DIM = { trac: "Gestion du trac", preparation: "Préparation", message: "Clarté du message", objections: "Gestion des objections", voix: "Voix", presence: "Présence", recit: "Récit", feedback: "Culture du retour" };
    var RISK = { trac: "Le trac prend la place du message : vous évitez des occasions de parler.", preparation: "L'ouverture et la conclusion, les deux moments que l'on retient, sont improvisées.", message: "Votre auditoire repart sans savoir ce qu'il devait retenir ou décider.", objections: "Une question hostile peut défaire en une minute ce que vous avez construit.", voix: "Une voix monotone ou trop rapide fait décrocher, même sur un bon fond.", presence: "Le corps contredit le propos : votre crédibilité en pâtit.", recit: "Sans exemple ni histoire, vos idées restent abstraites et s'oublient.", feedback: "Sans retour, vous répétez les mêmes erreurs sans les voir." };
    var ACT = { trac: "Apprenez par cœur vos trente premières secondes et faites trois expirations longues avant de prendre la parole.", preparation: "Écrivez mot à mot votre ouverture et votre conclusion, puis répétez debout, à voix haute, chronométré.", message: "Formulez votre message en une phrase, puis trois idées au plus pour le soutenir.", objections: "Listez les cinq objections les plus probables et préparez une réponse courte, avec une preuve, pour chacune.", voix: "Enregistrez deux minutes de parole et placez un silence là où le débit s'accélère.", presence: "Répétez debout, les deux pieds ancrés, en posant le regard sur une personne par idée.", recit: "Choisissez un exemple vécu par idée et racontez-le en trois temps : situation, tension, issue.", feedback: "Après votre prochaine intervention, demandez à une personne de confiance deux points forts et un axe de progrès." };
    var REL = { assurance: ["trac", "presence", "voix"], evoluer: ["message", "recit", "objections"], convaincre: ["objections", "message", "preparation"], mobiliser: ["message", "presence", "recit"], complexe: ["objections", "trac", "presence"] };
    var st = { i: 0, obj: null, a: {} };
    var ui = shell(diagEl, { title: "Diagnostic d'aisance orale" });
    var total = Q.length + 1;
    ui.back.addEventListener("click", function () { if (st.i > 0) { st.i--; render(); } });
    function render() {
      ui.foot.hidden = false; ui.back.disabled = st.i === 0;
      ui.bar.style.width = (Math.min(st.i, total) / total * 100) + "%";
      if (st.i >= total) return result();
      ui.count.textContent = "Question " + (st.i + 1) + " sur " + total;
      ui.phase.textContent = "";
      ui.hint.textContent = st.i === 0 ? "Votre objectif oriente la recommandation finale." : "Choisissez la réponse la plus proche de votre pratique.";
      if (st.i === 0) {
        ui.body.innerHTML = '<p class="q-title">Quel est votre objectif principal ?</p>' + optionsHtml(OBJ.map(function (o) { return [o[0], o[1], o[2]]; }), st.obj, "obj");
      } else {
        var q = Q[st.i - 1];
        ui.body.innerHTML = '<p class="q-title">' + esc(q[0]) + '</p>' + optionsHtml(q[2].map(function (t, j) { return [String(j + 1), t]; }), st.a[q[1]] ? String(st.a[q[1]]) : null, q[1]);
      }
      $$(".opt", ui.body).forEach(function (b) {
        b.addEventListener("click", function () {
          $$(".opt", ui.body).forEach(function (x) { x.setAttribute("aria-pressed", x === b); });
          if (st.i === 0) st.obj = b.getAttribute("data-v"); else st.a[b.getAttribute("data-k")] = +b.getAttribute("data-v");
          setTimeout(function () { st.i++; render(); }, 220);
        });
      });
    }
    function result() {
      ui.count.textContent = "Votre restitution"; ui.hint.textContent = "";
      var keys = Q.map(function (q) { return q[1]; });
      var sum = keys.reduce(function (s, k) { return s + st.a[k]; }, 0);
      var rel = REL[st.obj] || [];
      var sorted = keys.slice().sort(function (x, y) { return (st.a[x] - st.a[y]) || (rel.indexOf(x) < 0 ? 9 : rel.indexOf(x)) - (rel.indexOf(y) < 0 ? 9 : rel.indexOf(y)); });
      var prio = sorted.slice(0, 2);
      var forts = keys.filter(function (k) { return st.a[k] === 3; }).slice(0, 3);
      if (!forts.length) forts = sorted.slice(-2).reverse().filter(function (k) { return prio.indexOf(k) < 0; });
      var niveau = sum <= 13 ? ["Des fondations à poser", "Votre parole a besoin d'abord d'assurance et de méthode. C'est le point de départ le plus fréquent, et celui où les progrès sont les plus rapides."]
        : sum <= 19 ? ["Une pratique en progression", "Les bases sont là ; votre impact dépend encore des situations. Deux priorités bien travaillées feront la différence."]
        : ["Une maîtrise à affiner", "Vous maîtrisez l'essentiel. L'enjeu est désormais l'impact dans les situations décisives."];
      var o = OBJ.filter(function (x) { return x[0] === st.obj; })[0] || OBJ[0];
      var pk = o[3], note = "";
      if (st.obj === "convaincre" && sum <= 13) { pk = "eloquence-elite"; note = "Avant Parole d'Impact, consolider les fondamentaux vous fera gagner du temps."; }
      var p = LP.programmes[pk];
      store.set("diag", { date: Date.now(), a: st.a, obj: st.obj });
      var meters = keys.map(function (k) {
        var on = st.a[k];
        return '<div class="meter' + (prio.indexOf(k) > -1 ? " prio" : "") + '"><span>' + DIM[k] + '</span><span class="track" aria-label="' + on + ' sur 3"><i class="' + (on >= 1 ? "on" : "") + '"></i><i class="' + (on >= 2 ? "on" : "") + '"></i><i class="' + (on >= 3 ? "on" : "") + '"></i></span></div>';
      }).join("");
      var summary = "Diagnostic d'aisance orale. Objectif : " + o[1] + ". Priorités : " + prio.map(function (k) { return DIM[k]; }).join(", ") + ".";
      ui.body.innerHTML = '<div class="result fade-in">' +
        '<div class="result-head"><span class="eyebrow">' + esc(o[1]) + '</span><h3>' + niveau[0] + '</h3><p class="lede">' + niveau[1] + '</p></div>' +
        '<div class="result-cols"><div><h4>Votre profil déclaré</h4><div class="meters">' + meters + '</div></div>' +
        '<div><h4>Vos points forts</h4><p>' + forts.map(function (k) { return DIM[k]; }).join(", ") + '</p>' +
        '<h4 style="margin-top:28px">Vos deux priorités</h4><ul class="steps-list">' + prio.map(function (k) { return "<li><span><strong>" + DIM[k] + "</strong><br><span class=\"muted\">" + RISK[k] + "</span></span></li>"; }).join("") + '</ul></div></div>' +
        '<div><h4>Trois actions pour commencer</h4><ul class="steps-list">' + prio.map(function (k) { return "<li><span>" + ACT[k] + "</span></li>"; }).join("") + '<li><span>Mesurez votre débit et vos silences au <a class="link" href="' + LP.labo + '">Laboratoire de la parole</a>, puis recommencez dans deux semaines.</span></li></ul></div>' +
        '<div class="reco"><div class="ph"><img src="' + p.img + '" alt=""></div><div><span class="eyebrow">Programme recommandé · ' + p.n + '</span><h3>' + esc(p.title) + '</h3><p>' + esc(p.sub) + ' Il répond à votre objectif et travaille en priorité ' + prio.map(function (k) { return DIM[k].toLowerCase(); }).join(" et ") + '.' + (note ? " " + note : "") + '</p>' +
        '<div class="actions"><a class="btn" href="' + contactUrl({ besoin: "echange", formation: pk, message: summary }) + '">Être recontacté avec ce diagnostic ' + ARROW + '</a><a class="link" href="' + p.url + '">Lire la fiche du programme</a></div></div></div>' +
        '<div class="diag-mail" id="diag-mail"><h4>Recevoir votre restitution par courriel</h4><p>Votre profil, vos deux priorités, les trois actions et le programme recommandé, à conserver ou à transmettre.</p><form novalidate><input type="text" name="prenom" autocomplete="given-name" placeholder="Votre prénom" aria-label="Votre prénom" required><input type="email" name="email" autocomplete="email" placeholder="Votre courriel" aria-label="Votre adresse électronique" required><button class="btn" type="submit">Recevoir ma restitution</button><span class="hp" aria-hidden="true"><input type="text" name="site_web" tabindex="-1" autocomplete="off"></span><label class="consent"><input type="checkbox" name="ok" value="oui"> <span>J\'accepte que Lead Performant m\'adresse cette restitution et puisse me recontacter à son sujet, sans céder mes informations. <a href="/confidentialite/">Confidentialité</a>.</span></label><p class="dm-status" aria-live="polite"></p></form></div>' +
        '<p class="caveat">Ce diagnostic traduit vos propres réponses. Il oriente votre travail ; il ne constitue pas une mesure de votre éloquence. <button type="button" class="link" id="diag-again" style="margin-left:8px">Refaire le diagnostic</button></p></div>';
      fixImages(ui.body);
      var dm = $("#diag-mail form");
      if (dm) dm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var stt = $(".dm-status", dm), pr = dm.prenom.value.trim(), em = dm.email.value.trim();
        stt.className = "dm-status err";
        if (!pr) { stt.textContent = "Indiquez votre prénom."; dm.prenom.focus(); return; }
        if (!emailOk(em)) { stt.textContent = "Indiquez une adresse valide, par exemple nom@domaine.fr."; dm.email.focus(); return; }
        if (!dm.ok.checked) { stt.textContent = "Votre accord est nécessaire pour l'envoi."; return; }
        var lignes = ["Objectif : " + o[1], "Niveau : " + niveau[0] + ". " + niveau[1], "", "Votre profil déclaré (de 1 à 3) :"]
          .concat(keys.map(function (k) { return "- " + DIM[k] + " : " + st.a[k] + "/3"; }))
          .concat(["", "Vos points forts : " + forts.map(function (k) { return DIM[k]; }).join(", "), "", "Vos deux priorités :"])
          .concat(prio.map(function (k) { return "- " + DIM[k] + " : " + RISK[k]; }))
          .concat(["", "Trois actions pour commencer :"]).concat(prio.map(function (k) { return "- " + ACT[k]; }))
          .concat(["- Mesurez votre débit et vos silences au Laboratoire de la parole : " + (LP.site || "") + "laboratoire.html", "", "Programme recommandé : " + p.title + " — " + (LP.site ? LP.site.replace(/\/$/, "") : "") + p.url + (note ? "\n" + note : "")]);
        var b = $("button", dm); b.disabled = true; b.textContent = "Envoi en cours…";
        send({ type: "diagnostic", nom: pr, email: em, programme: p.title, besoin: "Diagnostic d'aisance orale", message: lignes.join("\n"), site_web: dm.site_web.value, consentement: "oui" })
          .then(function (res) { $("#diag-mail").innerHTML = '<p class="dm-ok">' + (res.preview ? "Aperçu : l'envoi n'est pas actif ici." : "C'est envoyé. Votre restitution arrive dans quelques minutes à " + esc(em) + " ; pensez à vérifier vos courriers indésirables.") + '</p>'; })
          .catch(function () { b.disabled = false; b.textContent = "Recevoir ma restitution"; stt.textContent = "L'envoi n'a pas abouti. Réessayez dans un instant ou écrivez à " + LP.email + "."; });
      });
      $("#diag-again").addEventListener("click", function () { st = { i: 0, obj: null, a: {} }; render(); diagEl.scrollIntoView({ behavior: "smooth", block: "start" }); });
    }
    render();
  }

  /* ------------------------------------------------ Atelier du leader */
  var atEl = $("[data-atelier]");
  if (atEl) {
    var TABS = [["vision", "Formuler une vision"], ["delegation", "Déléguer juste"], ["feedback", "Donner un feedback"]];
    var tab = "vision";
    var FIELDS = {
      vision: [["pourqui", "Pour qui ?", "Vos clients, vos usagers, votre territoire"], ["ambition", "Quelle ambition ?", "Ce que vous voulez rendre possible"], ["valeur", "Par quelle valeur distinctive ?", "Ce qui vous rend différent"], ["horizon", "À quel horizon ?", "Par exemple : d'ici dix-huit mois"]],
      delegation: [["tache", "La responsabilité à confier", "Par exemple : piloter le reporting mensuel"], ["qui", "À qui ?", "Prénom ou fonction"], ["comp", "Sa compétence sur cette tâche", "", "seg"], ["eng", "Son engagement sur cette tâche", "", "seg"], ["resultat", "Le résultat attendu et l'échéance", "Ce qui sera livré, et quand"]],
      feedback: [["fait", "Le fait observé", "Ce que vous avez vu ou entendu, sans interprétation"], ["impact", "Son impact", "Sur l'équipe, le client, le résultat"], ["attente", "Votre attente", "Ce que vous souhaitez voir désormais"], ["question", "Votre question d'ouverture", "Pour entendre le point de vue de l'autre"]]
    };
    function drawAt() {
      var d = store.get("atelier-" + tab, {});
      var form = FIELDS[tab].map(function (f) {
        if (f[3] === "seg") {
          var v = d[f[0]] || 2;
          return '<div class="field"><span class="label">' + f[1] + '</span><div class="seg" data-f="' + f[0] + '">' + [1, 2, 3, 4].map(function (n) { return '<button type="button" data-v="' + n + '" aria-pressed="' + (v === n) + '">' + n + '</button>'; }).join("") + '</div><div class="seg-legend"><span>Débutant</span><span>Autonome</span></div></div>';
        }
        return '<div class="field"><label for="at-' + f[0] + '">' + f[1] + '</label><textarea id="at-' + f[0] + '" data-f="' + f[0] + '" rows="2" style="min-height:76px" placeholder="' + esc(f[2]) + '">' + esc(d[f[0]] || "") + '</textarea></div>';
      }).join("");
      atEl.innerHTML = '<div class="tabs" role="tablist">' + TABS.map(function (t, i) { return '<button type="button" role="tab" data-t="' + t[0] + '" aria-selected="' + (t[0] === tab) + '"><span class="num">0' + (i + 1) + '</span>' + t[1] + '</button>'; }).join("") + '</div>' +
        '<div class="tool-body"><div class="workshop"><div style="display:grid;gap:20px;align-content:start">' + form + '</div><div class="deliverable"><span class="eyebrow">Votre livrable</span><div class="doc" id="at-doc"></div><div class="actions"><button type="button" class="btn" id="at-copy">Copier le livrable</button><button type="button" class="link" id="at-clear">Effacer</button></div></div></div></div>';
      $$("[role=tab]", atEl).forEach(function (b) { b.addEventListener("click", function () { tab = b.getAttribute("data-t"); drawAt(); }); });
      $$(".seg button", atEl).forEach(function (b) { b.addEventListener("click", function () { $$("button", b.parentNode).forEach(function (x) { x.setAttribute("aria-pressed", x === b); }); out(); }); });
      atEl.querySelector(".workshop").addEventListener("input", out);
      $("#at-copy").addEventListener("click", function () { copy($("#at-doc").innerText); });
      $("#at-clear").addEventListener("click", function () { store.set("atelier-" + tab, {}); drawAt(); });
      out();
    }
    function val() {
      var d = {};
      $$("textarea[data-f]", atEl).forEach(function (t) { d[t.getAttribute("data-f")] = t.value.trim(); });
      $$(".seg", atEl).forEach(function (s) { var b = $('[aria-pressed="true"]', s); d[s.getAttribute("data-f")] = b ? +b.getAttribute("data-v") : 2; });
      return d;
    }
    function blank(s, ph) { return s ? esc(s) : '<span class="muted">' + ph + '</span>'; }
    function out() {
      var d = val(); store.set("atelier-" + tab, d);
      var h = "";
      if (tab === "vision") {
        h = '<span class="t">Ma vision, en une phrase</span>D\'ici ' + blank(d.horizon, "[horizon]") + ', nous voulons ' + blank(d.ambition, "[ambition]") + ' pour ' + blank(d.pourqui, "[pour qui]") + ', grâce à ' + blank(d.valeur, "[valeur distinctive]") + '.\n\nAvant de la porter :\n· Tient-elle en une respiration ?\n· Un membre de l\'équipe peut-il la répéter sans la relire ?\n· Ouvre-t-elle chacune de vos prochaines prises de parole ?\n\nUn leader sans vision gère ; un leader avec une vision entraîne.';
      } else if (tab === "delegation") {
        var c = d.comp, e = d.eng, post, how, ctrl;
        if (c <= 2 && e >= 3) { post = "Diriger"; how = "Donner des consignes précises, montrer, vérifier souvent."; ctrl = ["Démonstration et consignes écrites", "Point court chaque semaine", "Bilan à l'échéance"]; }
        else if (c <= 2) { post = "Entraîner"; how = "Expliquer le pourquoi, décider ensemble, encourager chaque progrès."; ctrl = ["Lancement : objectif, sens, limites", "Point hebdomadaire de décision commune", "Bilan et étape suivante"]; }
        else if (e <= 2) { post = "Soutenir"; how = "Écouter, encourager, laisser choisir la méthode."; ctrl = ["Lancement : résultat attendu, liberté de méthode", "Point d'écoute à mi-parcours", "Reconnaissance du résultat"]; }
        else { post = "Déléguer"; how = "Fixer le résultat et l'échéance, puis se retirer."; ctrl = ["Lancement : résultat, échéance, moyens", "Un seul point de contrôle final"]; }
        h = '<span class="t">Plan de délégation</span>Responsabilité : ' + blank(d.tache, "[à préciser]") + '\nConfiée à : ' + blank(d.qui, "[collaborateur]") + '\nCompétence ' + c + '/4 · Engagement ' + e + '/4\n\nPosture recommandée : ' + post.toUpperCase() + '\n' + how + '\n\nRésultat attendu : ' + blank(d.resultat, "[résultat et échéance]") + '\n\nPoints de contrôle\n' + ctrl.map(function (x, i) { return (i + 1) + ". " + x; }).join("\n");
      } else {
        h = '<span class="t">Trame de feedback</span>1. Le fait\n« J\'ai observé que ' + blank(d.fait, "[fait observable]") + '. »\n\n2. L\'impact\n« Cela a pour conséquence ' + blank(d.impact, "[impact]") + '. »\n\n3. L\'attente\n« Ce que j\'attends désormais, c\'est ' + blank(d.attente, "[attente précise]") + '. »\n\n4. L\'ouverture\n« ' + (d.question ? esc(d.question) : "Comment le vois-tu de ton côté ?") + ' »\n\nConclure : une action convenue et une date de suivi.';
      }
      $("#at-doc").innerHTML = h;
    }
    drawAt();
  }

  /* ------------------------------------------------ Parcours Parole d'Impact */
  var pcEl = $("[data-parcours]");
  if (pcEl) {
    var S = {
      situation: ["Dans quelle situation devez-vous convaincre ?", [["comite", "Présenter un projet à un comité de direction"], ["client", "Convaincre un client ou un partenaire"], ["equipe", "Faire adhérer une équipe à une décision"], ["public", "Intervenir devant un public ou en conférence"], ["medias", "Répondre aux médias ou à des questions difficiles"], ["autre", "Une autre situation à fort enjeu"]]],
      resultat: ["Quel résultat voulez-vous obtenir ?", [["decision", "Obtenir une décision ou un arbitrage"], ["adhesion", "Susciter l'adhésion et mobiliser"], ["credibilite", "Renforcer ma crédibilité et mon impact"], ["objections", "Répondre aux objections et aux questions difficiles"]]],
      echeance: ["Quand cette intervention aura-t-elle lieu ?", [["30", "Dans moins de 30 jours"], ["90", "Dans un à trois mois"], ["plus", "Dans plus de trois mois"], ["sans", "Sans échéance précise"]]],
      aisance: ["Comment évaluez-vous votre aisance actuelle ?", [["faible", "Je manque d'assurance ou de méthode"], ["variable", "Je suis à l'aise, mais mon impact varie"], ["cap", "Je maîtrise les fondamentaux et veux franchir un cap"]]],
      decision: ["Quelle décision doit prendre votre auditoire ?", [["projet", "Valider un projet ou une proposition"], ["arbitrage", "Arbitrer entre plusieurs options"], ["budget", "Accorder un budget ou des ressources"], ["changement", "Adhérer à un changement"], ["strategie", "Prendre une décision stratégique"]]],
      role: ["Quel est votre rôle ?", [["porte", "Je porte et défends la recommandation"], ["collectif", "Je présente un dossier préparé collectivement"], ["conseil", "Je conseille les décideurs"], ["perimetre", "Je dois faire valider mon périmètre d'action"]]],
      difficulte: ["Qu'est-ce qui risque le plus de limiter votre impact ?", [["essentiel", "Aller à l'essentiel et hiérarchiser"], ["objections", "Répondre aux objections"], ["hesitants", "Obtenir l'adhésion de décideurs hésitants"], ["temps", "Convaincre dans un temps limité"], ["presence", "Ma présence, ma voix, mon assurance"]]],
      position: ["Quelle est la position dominante de votre auditoire ?", [["favorable", "Plutôt favorable"], ["partage", "Des positions partagées"], ["reserve", "Des réserves ou des oppositions prévisibles"], ["inconnu", "Je ne connais pas encore leurs positions"]]],
      date: ["À quelle date précise aura lieu la présentation ?", [["7", "Dans un à sept jours"], ["15", "Dans huit à quinze jours"], ["30", "Dans seize à trente jours"], ["tbd", "Date encore à confirmer"]]]
    };
    var PRIO = [["structure", "Structurer mon message", "Faire ressortir l'essentiel et faciliter la décision"], ["memorable", "Rendre mon propos mémorable", "Illustrer et construire un récit convaincant"], ["presence", "Renforcer ma présence orale", "Voix, rythme, regard et posture"], ["objections", "Répondre aux objections", "Défendre mes arguments sans perdre le fil"], ["pression", "Garder mon impact sous pression", "L'imprévu et les questions difficiles"]];
    var LEVER = { structure: ["Structurer pour décider", "Faire apparaître dès l'ouverture le problème, la recommandation, les options et la décision attendue."], memorable: ["Rendre la recommandation mémorable", "Un fil narratif, un exemple décisif, un chiffre qui reste."], presence: ["Incarner la recommandation", "Une voix posée, des silences avant les chiffres clés, un regard adressé aux décideurs."], objections: ["Défendre la recommandation", "Relier chaque argument aux priorités de l'auditoire : résultats, risques, coûts, délais."], pression: ["Garder l'impact sous pression", "Répondre sans perdre le fil et conclure par une demande explicite."] };
    var lab = function (k, v) { var f = (S[k] ? S[k][1] : PRIO).filter(function (o) { return o[0] === v; })[0]; return f ? f[1] : ""; };
    var FLOW = ["situation", "resultat", "echeance", "aisance", "prio", "restit", "decision", "role", "difficulte", "position", "date", "orient", "form", "done"];
    var PHASE = { situation: "Votre situation", resultat: "Votre situation", echeance: "Votre situation", aisance: "Votre situation", prio: "Vos priorités", restit: "Votre restitution", decision: "Affiner", role: "Affiner", difficulte: "Affiner", position: "Affiner", date: "Affiner", orient: "Votre orientation", form: "Vos coordonnées", done: "Confirmation" };
    var pc = { i: 0, a: {}, prio: [], f: {} };
    var ui2 = shell(pcEl, { title: "Parole d'Impact" });
    ui2.phase.innerHTML = "";
    var steps = function () { return FLOW.filter(function (s) { return !(s === "date" && pc.a.echeance !== "30"); }); };
    ui2.back.addEventListener("click", function () { if (pc.i > 0) { pc.i--; draw(); } });
    function go(n) { pc.i += n; draw(); pcEl.scrollIntoView({ behavior: "smooth", block: "start" }); }
    function draw() {
      var fl = steps(), s = fl[pc.i];
      ui2.bar.style.width = (pc.i / (fl.length - 1) * 100) + "%";
      ui2.count.textContent = PHASE[s];
      ui2.back.disabled = pc.i === 0 || s === "done";
      ui2.hint.textContent = "";
      if (S[s]) {
        ui2.body.innerHTML = '<p class="q-title">' + S[s][0] + '</p>' + optionsHtml(S[s][1], pc.a[s], s, false, S[s][1].length > 4);
        $$(".opt", ui2.body).forEach(function (b) { b.addEventListener("click", function () { $$(".opt", ui2.body).forEach(function (x) { x.setAttribute("aria-pressed", x === b); }); pc.a[s] = b.getAttribute("data-v"); setTimeout(function () { pc.i++; draw(); }, 220); }); });
        ui2.hint.textContent = pc.i < 4 ? "Aucune coordonnée n'est demandée à ce stade." : "";
        return;
      }
      if (s === "prio") {
        ui2.body.innerHTML = '<p class="q-title">Quelles sont vos deux priorités ?</p><p class="muted" style="margin:-12px 0 24px">Deux au maximum : c\'est ce qui rend la préparation efficace.</p>' + optionsHtml(PRIO, pc.prio, "prio", true) + '<p class="counter"><span id="pc-n">' + pc.prio.length + '</span> sur 2 sélectionnées</p><div class="actions" style="margin-top:24px"><button type="button" class="btn" id="pc-next"' + (pc.prio.length ? "" : " disabled") + '>Voir ma restitution ' + ARROW + '</button></div>';
        $$(".opt", ui2.body).forEach(function (b) {
          b.addEventListener("click", function () {
            var v = b.getAttribute("data-v"), ix = pc.prio.indexOf(v);
            if (ix > -1) pc.prio.splice(ix, 1); else { if (pc.prio.length >= 2) pc.prio.shift(); pc.prio.push(v); }
            $$(".opt", ui2.body).forEach(function (x) { x.setAttribute("aria-pressed", pc.prio.indexOf(x.getAttribute("data-v")) > -1); });
            $("#pc-n").textContent = pc.prio.length; $("#pc-next").disabled = !pc.prio.length;
          });
        });
        $("#pc-next").addEventListener("click", function () { go(1); });
        return;
      }
      if (s === "restit") {
        var a = pc.a;
        var lev = pc.prio.map(function (k) { return LEVER[k]; });
        ["structure", "objections", "pression"].forEach(function (k) { if (lev.length < 3 && pc.prio.indexOf(k) < 0) lev.push(LEVER[k]); });
        var titre = a.aisance === "variable" ? "Vous savez prendre la parole. Il reste à faire pencher la décision." : a.aisance === "cap" ? "Vos fondamentaux sont solides. L'enjeu est de franchir un cap." : "Votre enjeu mérite une méthode solide.";
        var enjeu = { decision: "obtenir une décision", adhesion: "susciter l'adhésion", credibilite: "renforcer votre crédibilité", objections: "tenir face aux objections" }[a.resultat];
        var qui = a.situation === "client" ? "mon interlocuteur" : a.situation === "equipe" ? "l'équipe" : a.situation === "public" || a.situation === "medias" ? "mon public" : "le comité";
        ui2.body.innerHTML = '<div class="result fade-in"><div class="result-head"><span class="eyebrow">' + esc(lab("situation", a.situation)) + '</span><h3>' + titre + '</h3><p class="lede">Votre objectif : ' + enjeu + '. ' + (a.echeance === "30" ? "Votre échéance est à moins de trente jours : la préparation doit être ciblée." : "Votre calendrier permet un parcours de progression.") + '</p></div>' +
          '<div><h4>Les trois leviers de votre préparation</h4><div class="g3">' + lev.slice(0, 3).map(function (l, i) { return '<div class="item" style="padding-top:20px"><span class="num" style="font-size:1.6rem">0' + (i + 1) + '</span><h3 style="font-size:1.3rem">' + l[0] + '</h3><p>' + l[1] + '</p></div>'; }).join("") + '</div></div>' +
          '<div class="exercise"><span class="eyebrow">Votre premier exercice</span><p class="big">« À l\'issue de cette présentation, je veux que ' + qui + ' décide de… parce que… »</p><p class="muted">Si la décision demandée ne tient pas en une phrase, la présentation mérite d\'être restructurée avant de travailler la forme.</p></div>' +
          (a.aisance === "faible" ? '<p class="muted">Vous indiquez manquer d\'assurance ou de méthode : <a class="link" href="' + LP.programmes["eloquence-elite"].url + '">Éloquence Élite</a> peut consolider les fondamentaux avant ou pendant la préparation.</p>' : "") +
          '<div class="actions"><button type="button" class="btn" id="pc-next">Affiner ma stratégie ' + ARROW + '</button><a class="link" href="' + LP.labo + '">M\'entraîner d\'abord au Laboratoire</a></div>' +
          '<p class="caveat">Cette restitution est une hypothèse de travail fondée sur vos réponses. Elle ne mesure pas votre éloquence et ne garantit pas l\'obtention de la décision.</p></div>';
        $("#pc-next").addEventListener("click", function () { go(1); });
        return;
      }
      if (s === "orient") {
        var b = pc.a, urgent = b.echeance === "30" && (b.date === "7" || b.date === "15");
        var fort = ["budget", "strategie", "arbitrage"].indexOf(b.decision) > -1, obst = b.position === "reserve" || b.position === "partage" || b.difficulte === "objections";
        var niv;
        if (urgent && (fort || obst)) niv = ["Accompagnement prioritaire", "Enjeu élevé, délai court, obstacles identifiés.", "Un échange de cadrage rapide permet de vérifier la faisabilité d'une préparation individuelle avant votre échéance."];
        else if (b.echeance === "30" && b.date === "tbd" || b.echeance === "sans") niv = ["Échange de cadrage", "Le calendrier reste à préciser.", "Clarifions d'abord la nature exacte de la décision et le calendrier, avant de recommander un format."];
        else niv = ["Parcours structuré", "Besoin de méthode, d'argumentation et d'entraînement.", "Le parcours Parole d'Impact permet une préparation en plusieurs temps, avec répétitions filmées et ajustements."];
        pc.niveau = niv[0];
        var A1 = { projet: "Structurer la recommandation et démontrer sa valeur.", arbitrage: "Comparer les scénarios et rendre explicites les critères de décision.", budget: "Préparer le dossier de valeur : coûts, bénéfices, risques.", changement: "Travailler les bénéfices, les résistances et les conditions d'adhésion.", strategie: "Travailler la synthèse exécutive, les scénarios et leurs conséquences." }[b.decision];
        var A2 = { favorable: "Sécuriser l'accord et accélérer la décision.", partage: "Adapter l'argumentation aux intérêts de chaque décideur.", reserve: "Organiser une simulation de questions difficiles.", inconnu: "Cartographier les positions avant de finaliser le message." }[b.position];
        var A3 = b.echeance !== "30" ? "Préparer, répéter, ajuster en plusieurs séances." : { "7": "Prioriser les corrections à fort impact et la répétition.", "15": "Structure d'abord, puis deux répétitions complètes.", "30": "Préparation, entraînement et ajustements successifs.", tbd: "Préparer une version courte et une version longue." }[b.date];
        ui2.body.innerHTML = '<div class="result fade-in"><div class="result-head"><span class="eyebrow">Votre orientation</span><h3>' + niv[0] + '</h3><p class="lede">' + niv[2] + '</p></div>' +
          '<div class="g3">' + [["Sur la décision", A1], ["Sur l'auditoire", A2], ["Sur le calendrier", A3]].map(function (x) { return '<div class="item" style="padding-top:20px"><span class="eyebrow">' + x[0] + '</span><p style="color:var(--ink)">' + x[1] + '</p></div>'; }).join("") + '</div>' +
          '<div><h4>L\'échange de cadrage permettra de</h4><ul class="steps-list"><li><span>Clarifier la décision attendue et le public</span></li><li><span>Identifier les arguments et les objections prioritaires</span></li><li><span>Déterminer les modalités de préparation selon votre échéance</span></li></ul></div>' +
          '<div class="actions"><button type="button" class="btn" id="pc-next">Préparer ma présentation avec un expert ' + ARROW + '</button><span class="note">Gratuit et sans engagement.</span></div></div>';
        $("#pc-next").addEventListener("click", function () { go(1); });
        return;
      }
      if (s === "form") {
        var f = pc.f;
        var fonctions = ["Cadre", "Manager", "Dirigeant", "Consultant ou expert", "Élu ou responsable public", "Autre fonction"];
        ui2.body.innerHTML = '<p class="q-title">Parlons de votre prise de parole</p><p class="muted" style="margin:-12px 0 30px;max-width:60ch">Vos réponses sont jointes à votre demande : le premier échange partira directement de votre situation.</p>' +
          '<form class="form" id="pc-form" novalidate>' +
          '<div class="field"><label for="pc-nom">Nom et prénom</label><input id="pc-nom" type="text" autocomplete="name" value="' + esc(f.nom) + '"><span class="err" id="pe-nom"></span></div>' +
          '<div class="field"><label for="pc-email">Courriel professionnel</label><input id="pc-email" type="email" autocomplete="email" value="' + esc(f.email) + '"><span class="err" id="pe-email"></span></div>' +
          '<div class="field"><label for="pc-fonction">Votre fonction</label><select id="pc-fonction"><option value="">Choisir</option>' + fonctions.map(function (x) { return '<option' + (f.fonction === x ? " selected" : "") + '>' + x + '</option>'; }).join("") + '</select><span class="err" id="pe-fonction"></span></div>' +
          '<div class="field"><label for="pc-org">Organisation <span class="opt">(facultatif)</span></label><input id="pc-org" type="text" autocomplete="organization" value="' + esc(f.org) + '"></div>' +
          '<div class="field full"><label for="pc-msg">Votre enjeu en quelques lignes <span class="opt">(facultatif)</span></label><textarea id="pc-msg" placeholder="Contexte, public, décision recherchée">' + esc(f.msg) + '</textarea></div>' +
          '<div class="field full"><span class="label">Format d\'échange souhaité</span><div class="checks">' + ["Visioconférence", "Téléphone", "Recevoir une proposition écrite"].map(function (x, i) { return '<label><input type="radio" name="pc-fmt" value="' + x + '"' + ((f.fmt || "Visioconférence") === x ? " checked" : "") + '> ' + x + '</label>'; }).join("") + '</div></div>' +
          '<div class="hp" aria-hidden="true"><label for="pc-site">Ne pas remplir</label><input id="pc-site" type="text" tabindex="-1" autocomplete="off"></div>' +
          '<div class="field full"><label class="consent"><input type="checkbox" id="pc-ok"' + (f.ok ? " checked" : "") + '> <span>J\'accepte que Lead Performant utilise ces informations uniquement pour répondre à ma demande, sans les céder ni les utiliser à d\'autres fins.</span></label><span class="err" id="pe-ok"></span></div>' +
          '<div class="full" id="pc-status" hidden></div>' +
          '<div class="full actions"><button class="btn" type="submit" id="pc-send">Transmettre ma demande ' + ARROW + '</button><span class="note">Réponse sous deux jours ouvrés.</span></div></form>';
        $("#pc-form").addEventListener("submit", function (ev) {
          ev.preventDefault();
          f.nom = $("#pc-nom").value.trim(); f.email = $("#pc-email").value.trim(); f.fonction = $("#pc-fonction").value; f.org = $("#pc-org").value.trim(); f.msg = $("#pc-msg").value.trim(); f.ok = $("#pc-ok").checked;
          var r = $('input[name="pc-fmt"]:checked'); f.fmt = r ? r.value : "Visioconférence";
          var errs = { nom: !f.nom && "Indiquez votre nom.", email: !emailOk(f.email) && "Indiquez une adresse valide, par exemple nom@entreprise.fr.", fonction: !f.fonction && "Choisissez votre fonction.", ok: !f.ok && "Votre accord est nécessaire pour traiter la demande." };
          var bad = false;
          Object.keys(errs).forEach(function (k) { var e = $("#pe-" + k); e.textContent = errs[k] || ""; var fld = e.closest(".field"); if (fld) fld.classList.toggle("invalid", !!errs[k]); if (errs[k]) bad = true; });
          if (bad) { var first = $(".field.invalid input, .field.invalid select", pcEl); if (first) first.focus(); return; }
          var recap = ["Situation : " + lab("situation", pc.a.situation), "Résultat visé : " + lab("resultat", pc.a.resultat), "Échéance : " + lab("echeance", pc.a.echeance) + (pc.a.date ? " (" + lab("date", pc.a.date) + ")" : ""), "Aisance : " + lab("aisance", pc.a.aisance), "Priorités : " + pc.prio.map(function (k) { return lab("prio", k); }).join(", "), "Décision attendue : " + lab("decision", pc.a.decision), "Rôle : " + lab("role", pc.a.role), "Difficulté principale : " + lab("difficulte", pc.a.difficulte), "Position de l'auditoire : " + lab("position", pc.a.position), "Orientation proposée : " + pc.niveau, "Format souhaité : " + f.fmt].join("\n");
          var btn = $("#pc-send"); btn.disabled = true; btn.textContent = "Envoi en cours…";
          send({ type: "parcours-parole-d-impact", profil: "professionnel", besoin: pc.niveau === "Accompagnement prioritaire" ? "coaching (prioritaire)" : "coaching", programme: "parole-d-impact", nom: f.nom, email: f.email, fonction: f.fonction, organisation: f.org, message: (f.msg ? f.msg + "\n\n" : "") + recap, site_web: $("#pc-site").value, consentement: "oui" })
            .then(function (res) { pc.preview = !!res.preview; pc.i++; draw(); })
            .catch(function () {
              btn.disabled = false; btn.innerHTML = "Transmettre ma demande " + ARROW;
              var st = $("#pc-status"); st.hidden = false; st.className = "full form-status";
              st.innerHTML = "La demande n'a pas pu être transmise. Réessayez dans un instant, ou écrivez directement à <b>" + LP.email + "</b> ou appelez le <b>" + LP.phone + "</b>.";
            });
        });
        return;
      }
      if (s === "done") {
        ui2.hint.textContent = "";
        ui2.body.innerHTML = '<div class="confirm fade-in"><span class="eyebrow">' + (pc.preview ? "Aperçu" : "Demande transmise") + '</span><h2>' + (pc.preview ? "Votre demande est prête." : "Merci, votre demande a bien été transmise.") + '</h2>' +
          '<p class="muted">' + (pc.preview ? "Dans cet aperçu, l'envoi n'est pas actif. Une fois le site en ligne, la demande part directement au cabinet et un accusé de réception est adressé à " + esc(pc.f.email) + "." : "Un accusé de réception vient d'être envoyé à " + esc(pc.f.email) + ". Votre situation et vos réponses accompagnent la demande.") + '</p>' +
          '<ol><li>Votre demande est étudiée' + (pc.niveau === "Accompagnement prioritaire" ? ", en priorité compte tenu de votre échéance." : ".") + '</li><li>Nous vous proposons un créneau sous deux jours ouvrés, au format choisi.</li><li>Le premier échange de trente minutes cadre la préparation ; une proposition écrite suit.</li></ol>' +
          '<div class="actions"><a class="btn btn-ghost" href="' + LP.labo + '">En attendant, s\'entraîner au Laboratoire</a></div></div>';
      }
    }
    draw();
  }

  /* ------------------------------------------------ Contact */
  var cEl = $("[data-contact]");
  if (cEl) {
    var form = $("#contact-form");
    var qs = new URLSearchParams(LP.single ? (LP.query || "") : location.search);
    var setSel = function (id, v) { var s = $(id); if (s && v && $('option[value="' + v + '"]', s)) s.value = v; };
    var besoin = qs.get("besoin"); if (besoin === "devis" || besoin === "formation" || besoin === "atelier" || besoin === "coaching" || besoin === "intervention" || besoin === "echange") setSel("#f-besoin", besoin);
    setSel("#f-profil", qs.get("profil"));
    setSel("#f-programme", qs.get("formation") || qs.get("programme"));
    if (qs.get("message")) $("#f-message").value = qs.get("message");
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var v = function (id) { return $(id).value.trim(); };
      var errs = { profil: !v("#f-profil") && "Indiquez qui vous êtes.", besoin: !v("#f-besoin") && "Choisissez votre besoin.", nom: !v("#f-nom") && "Indiquez votre nom.", email: !emailOk(v("#f-email")) && "Indiquez une adresse valide, par exemple nom@domaine.fr.", message: !v("#f-message") && "Décrivez votre besoin en quelques mots.", consent: !$("#f-consent").checked && "Votre accord est nécessaire pour traiter la demande." };
      var bad = false;
      Object.keys(errs).forEach(function (k) { var e = $("#e-" + k); e.textContent = errs[k] || ""; var fld = e.closest(".field"); if (fld) fld.classList.toggle("invalid", !!errs[k]); if (errs[k]) bad = true; });
      if (bad) { var first = $(".field.invalid input, .field.invalid select, .field.invalid textarea", form); if (first) first.focus(); return; }
      var data = { type: "contact", profil: $("#f-profil").selectedOptions[0].text, besoin: $("#f-besoin").selectedOptions[0].text, programme: v("#f-programme") ? $("#f-programme").selectedOptions[0].text : "", nom: v("#f-nom"), organisation: v("#f-organisation"), email: v("#f-email"), telephone: v("#f-tel"), message: v("#f-message"), disponibilites: $$('input[name="disponibilites"]:checked', form).map(function (x) { return x.value; }).join(", "), site_web: v("#f-site"), consentement: "oui" };
      var btn = $("#f-submit"); btn.disabled = true; btn.textContent = "Envoi en cours…";
      send(data).then(function (res) {
        cEl.innerHTML = '<div class="confirm fade-in"><span class="eyebrow">' + (res.preview ? "Aperçu" : "Demande transmise") + '</span><h2>' + (res.preview ? "Votre demande est prête." : "Merci, " + esc(data.nom.split(" ")[0]) + ". Votre demande a bien été transmise.") + '</h2><p class="muted">' + (res.preview ? "Dans cet aperçu, l'envoi n'est pas actif. Une fois le site en ligne, la demande part directement au cabinet et un accusé de réception est adressé à " + esc(data.email) + "." : "Un accusé de réception vient d'être envoyé à " + esc(data.email) + ".") + '</p><ol><li>Votre demande est étudiée par le cabinet.</li><li>Nous vous répondons sous deux jours ouvrés pour convenir d\'un premier appel.</li><li>Après cet échange de trente minutes, vous recevez une proposition écrite.</li></ol></div>';
        cEl.scrollIntoView({ behavior: "smooth", block: "start" });
      }).catch(function () {
        btn.disabled = false; btn.innerHTML = "Envoyer ma demande " + ARROW;
        var st = $("#form-status"); st.hidden = false; st.className = "full form-status";
        st.innerHTML = "La demande n'a pas pu être transmise. Réessayez dans un instant, ou écrivez directement à <b>" + LP.email + "</b> ou appelez le <b>" + LP.phone + "</b>.";
      });
    });
  }
})();