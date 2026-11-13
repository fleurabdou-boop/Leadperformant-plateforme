/* Lead Performant — contenus publiés depuis l'espace de publication (articles, interventions, vidéos) */
(function () {
  "use strict";
  var LP = window.LP || {};
  var SEED = window.LP_SEED || { articles: [], interventions: [] };
  var esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); };
  var ARROW = '<span class="arrow" aria-hidden="true">→</span>';
  var CATS = { television: "Télévision", ateliers: "Formations et ateliers", presse: "Presse", diplomatie: "Diplomatie", conference: "Conférences" };
  var demo = LP.mode !== "prod";

  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function now() { var d = new Date(); return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) + "T" + pad(d.getHours()) + ":" + pad(d.getMinutes()); }
  function live(x) { return x.statut === "publie" && (x.publication || "9999") <= now(); }
  function sorted(l) { return l.slice().sort(function (a, b) { return (b.publication || "").localeCompare(a.publication || ""); }); }
  function link(target) {
    if (LP.mode === "prod") return "/" + target;
    if (LP.mode === "single") { var t = target.split("#")[0].split("?")[0].replace(/\.html$/, "").replace(/^\/|\/$/g, "").replace(/\//g, "-"); return "#" + (t || "accueil"); }
    return LP.base + target + (target === "" || /\/$/.test(target) ? "index.html" : "");
  }
  var LP_ART = {"1544531586-fde5298cdd40": "voix", "1542744173-8e7e53415bb0": "reseau", "1560439513-74b037a25d84": "embleme", "1551836022-4c4c79ecde51": "dialogue", "1758691736490-03d39c292d7a": "onde", "1517245386807-bb43f82c33c4": "grille", "1758518731462-d091b0b4ed0d": "boussole", "1587825140708-dfaf72ae4b04": "embleme", "1629327896333-7ecec1515ae5": "onde", "1475721027785-f74eccf877e2": "papier-1", "1710360683896-e0506fb3579a": "papier-2", "1582192730841-2a682d7375f9": "tribune", "1557804506-669a67965ba0": "grille", "1540575467063-178a50c2df87": "amphi", "1531058020387-3be344556be6": "tribune", "1758270704587-43339a801396": "amphi", "1758691736483-5f600b509962": "papier-3", "1573165662973-4ab3cf3d3508": "papier-2"};
  function asset(v) {
    var um = v && /images\.unsplash\.com\/photo-([0-9a-f]+-[0-9a-f]+)/.exec(v);
    if (um) return "/assets/lp/art/" + (LP_ART[um[1]] || "papier-1") + ".svg";
    if (!v || /^(https?:|data:|blob:)/.test(v)) return v || "";
    if (LP.mode === "prod") return v.charAt(0) === "/" ? v : "/" + v;
    return LP.site + v.replace(/^\//, "");
  }
  function articleUrl(a) {
    if (a.legacy_url && a.maj === a.seed_maj) return link(a.legacy_url);
    if (LP.mode === "prod") return "/ressources/article.php?slug=" + encodeURIComponent(a.slug);
    if (LP.mode === "single") return "#ressources-lire?slug=" + encodeURIComponent(a.slug);
    return link("ressources/lire/") + "#" + a.slug;
  }
  function store() { try { var v = localStorage.getItem("lp:cms"); return v ? JSON.parse(v) : null; } catch (e) { return null; } }
  function load() {
    if (demo) { var d = store() || SEED; return Promise.resolve({ articles: sorted(d.articles.filter(live)), interventions: sorted(d.interventions.filter(live)), all: d }); }
    return fetch(LP.api, { headers: { Accept: "application/json" }, cache: "no-store" }).then(function (r) { return r.json(); })
      .catch(function () { return { articles: sorted(SEED.articles.filter(live)), interventions: sorted(SEED.interventions.filter(live)) }; });
  }

  /* Mise en forme : "## " intertitre, "- " liste, "> " encadré, **gras**, [lien](https://…) */
  function inline(s) {
    return esc(s).replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>").replace(/\[([^\]]+)\]\(((?:https?:\/\/|\/)[^\s)]*)\)/g, function (m, t, u) {
      var ext = /^http/.test(u) && u.indexOf("leadperformant.com") < 0;
      return '<a class="link" href="' + u + '"' + (ext ? ' target="_blank" rel="noopener"' : "") + ">" + t + "</a>";
    });
  }
  function md(t) {
    return String(t || "").replace(/\r/g, "").trim().split(/\n\s*\n/).map(function (block) {
      var lines = block.split("\n").map(function (l) { return l.replace(/\s+$/, ""); }).filter(Boolean), out = "";
      if (!lines.length) return "";
      if (lines[0].indexOf("## ") === 0) { out += "<h2>" + inline(lines.shift().slice(3)) + "</h2>"; if (!lines.length) return out; }
      if (lines.every(function (l) { return l.indexOf("- ") === 0; })) return out + "<ul>" + lines.map(function (l) { return "<li>" + inline(l.slice(2)) + "</li>"; }).join("") + "</ul>";
      if (lines[0].indexOf("> ") === 0) return out + '<div class="aside"><p>' + inline(lines.map(function (l) { return l.replace(/^>\s?/, ""); }).join(" ")) + "</p></div>";
      return out + "<p>" + inline(lines.join(" ")) + "</p>";
    }).join("");
  }
  window.LPCMS = { md: md, live: live, now: now, sorted: sorted, articleUrl: articleUrl, link: link, asset: asset, CATS: CATS, esc: esc };

  /* ------------------------------------------------ Rendus (miroir des rendus statiques) */
  function video(e) {
    var th = asset(e.vignette);
    return '<div class="video" data-yt="' + esc(e.youtube) + '">' + (th ? '<img src="' + esc(th) + '" alt="" loading="lazy">' : "") + '<button type="button" aria-label="Lire la vidéo : ' + esc(e.titre) + '"><span>▶ Lire la vidéo</span></button></div>';
  }
  function entry(e) {
    var media = e.youtube ? video(e) : e.image ? '<div class="ph r169"><img src="' + esc(asset(e.image)) + '" alt="" loading="lazy"></div>' : "";
    var lk = e.lien ? '<a class="link" href="' + esc(e.lien) + '" target="_blank" rel="noopener">' + esc(e.lien_libelle || "En savoir plus") + " " + ARROW + "</a>"
      : e.youtube ? '<a class="link" href="https://www.youtube.com/watch?v=' + esc(e.youtube) + '" target="_blank" rel="noopener">Voir sur YouTube ' + ARROW + "</a>" : "";
    return '<article class="entry' + (media ? "" : " no-media") + '" data-cat="' + esc(e.categorie) + '"><div class="when"><span class="k">' + (CATS[e.categorie] || "") + "</span><span>" + esc(e.meta) + "</span></div><div><h3>" + esc(e.titre) + "</h3><p>" + esc(e.texte) + "</p>" + lk + "</div>" + media + "</article>";
  }
  function homeInt(items) {
    var lead = items.filter(function (e) { return e.youtube; })[0];
    var others = items.filter(function (e) { return e !== lead; }).slice(0, 4);
    var feat = lead ? '<div class="int-feature">' + video(lead) + '<div class="int-cap"><span class="k">' + (CATS[lead.categorie] || "") + " · " + esc(lead.meta) + "</span><h3>" + esc(lead.titre) + "</h3><p>" + esc(lead.texte) + "</p></div></div>" : "";
    return feat + '<ul class="int-list">' + others.map(function (e) { return '<li><span class="k">' + (CATS[e.categorie] || "") + "</span><strong>" + esc(e.titre) + '</strong><span class="m">' + esc(e.meta) + "</span></li>"; }).join("") + "</ul>";
  }
  function artCards(arts) {
    return arts.map(function (a) {
      return '<a class="pcard" href="' + articleUrl(a) + '"><div class="ph r43">' + (a.image ? '<img src="' + esc(asset(a.image)) + '" alt="" loading="lazy">' : "") + '</div><div class="body"><span class="tag">' + esc(a.categorie) + " · " + esc(a.lecture) + ' de lecture</span><h3>' + esc(a.titre) + "</h3><p>" + esc(a.resume || a.chapo) + '</p><div class="meta"><span>Lead Performant</span><span><b>Lire l\'article</b></span></div></div></a>';
    }).join("");
  }
  function artList(arts) {
    return arts.map(function (a) { return '<a class="art" href="' + articleUrl(a) + '"><span class="k"><b>' + esc(a.categorie) + "</b>" + esc(a.lecture) + " de lecture</span><h3>" + esc(a.titre) + "</h3><p>" + esc(a.resume || a.chapo) + "</p></a>"; }).join("");
  }
  function articleView(a, all) {
    var p = a.programme && LP.programmes && LP.programmes[a.programme];
    var others = all.filter(function (x) { return x.slug !== a.slug; }).slice(0, 2);
    return '<div class="article-head fade-in"><span class="eyebrow">' + esc(a.categorie) + "</span><h1>" + esc(a.titre) + '</h1><div class="article-meta"><span><b>Lecture</b> ' + esc(a.lecture) + '</span><span><b>Auteur</b> Lead Performant</span></div></div>' +
      (a.statut !== "publie" || !live(a) ? '<p class="form-status" style="margin-top:24px">Aperçu : cet article n\'est pas encore visible du public' + (a.statut === "publie" ? " (publication prévue le " + esc(a.publication.replace("T", " à ")) + ")" : " (brouillon)") + ".</p>" : "") +
      (a.image ? '<div class="ph r21 framed" style="margin-top:44px"><img src="' + esc(asset(a.image)) + '" alt=""></div>' : "") +
      '<article class="article-body" style="margin-top:56px"><p class="chapo">' + esc(a.chapo) + "</p>" + md(a.corps) +
      (a.video ? '<div class="video" data-yt="' + esc(a.video) + '" style="margin-top:2em"><button type="button" aria-label="Lire la vidéo"><span>▶ Lire la vidéo</span></button></div>' : "") +
      (p ? '<div class="aside"><strong>Aller plus loin</strong><p>' + esc(p.title) + " : " + esc(p.sub) + '</p><p style="margin-top:12px"><a class="link" href="' + p.url + '">Découvrir le programme ' + ARROW + "</a></p></div>" : "") + "</article>" +
      (others.length ? '<div style="margin-top:80px"><span class="eyebrow">À lire aussi</span><div class="g2" style="margin-top:24px">' + artList(others) + "</div></div>" : "");
  }

  /* ------------------------------------------------ Hydratation */
  var blocks = document.querySelectorAll("[data-cms]");
  var legacy = document.querySelector("[data-legacy]");
  if (blocks.length || legacy) {
    load().then(function (d) {
      if (!d || !d.articles) return;
      Array.prototype.forEach.call(blocks, function (el) {
        var k = el.getAttribute("data-cms");
        if (k === "feed") { el.innerHTML = d.interventions.map(entry).join("") || '<p class="muted">Aucune intervention publiée pour le moment.</p>'; applyFilter(); }
        if (k === "home-int") el.innerHTML = homeInt(d.interventions);
        if (k === "home-art") el.innerHTML = artList(d.articles.slice(0, 3));
        if (k === "articles") el.innerHTML = artCards(d.articles) || '<p class="muted">Aucun article publié pour le moment.</p>';
        if (k === "article") {
          var slug = LP.mode === "single" ? new URLSearchParams(LP.query || "").get("slug") : decodeURIComponent(location.hash.slice(1));
          var pool = demo && d.all ? d.all.articles : d.articles;
          var a = pool.filter(function (x) { return x.slug === slug; })[0];
          el.innerHTML = a ? articleView(a, d.articles) : '<h1>Article introuvable</h1><p class="lede" style="margin-top:18px">Cet article n\'existe pas ou n\'est pas encore publié.</p><div class="actions" style="margin-top:28px"><a class="btn" href="' + link("ressources/") + '">Toutes les ressources</a></div>';
          if (a) document.title = a.titre + " | Lead Performant";
        }
        if (window.LPU && window.LPU.fixImages) window.LPU.fixImages(el);
      });
      if (legacy) {
        var s = legacy.getAttribute("data-legacy"), m = legacy.getAttribute("data-maj");
        var cur = d.articles.filter(function (x) { return x.slug === s; })[0];
        if (!cur || cur.maj !== m) {
          var target = cur ? articleUrl(cur) : (LP.mode === "prod" ? "/ressources/article.php?slug=" + s : null);
          if (target && LP.mode === "prod") location.replace(target);
          else if (target && LP.mode === "preview") location.replace(target);
        }
      }
    });
  }

  /* ------------------------------------------------ Filtres et vidéos (délégation, fonctionne après hydratation) */
  var currentFilter = "tout";
  function applyFilter() {
    Array.prototype.forEach.call(document.querySelectorAll("[data-cms='feed'] .entry"), function (e) { e.hidden = !(currentFilter === "tout" || e.getAttribute("data-cat") === currentFilter); });
  }
  if (!window.__lpCmsBound) {
    window.__lpCmsBound = true;
    document.addEventListener("click", function (ev) {
      var f = ev.target.closest && ev.target.closest(".filters button[data-f]");
      if (f) {
        currentFilter = f.getAttribute("data-f");
        Array.prototype.forEach.call(f.parentNode.querySelectorAll("button"), function (x) { x.setAttribute("aria-pressed", x === f); });
        applyFilter(); return;
      }
      var b = ev.target.closest && ev.target.closest(".video button");
      if (b) {
        var v = b.closest(".video"), id = v.getAttribute("data-yt");
        if (LP.mode !== "prod" && LP.mode !== "single") { if (window.LPU) window.LPU.toast("La lecture intégrée sera active sur le site en ligne"); return; }
        v.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0" title="Vidéo YouTube" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
      }
    });
  } else { currentFilter = "tout"; }
})();
