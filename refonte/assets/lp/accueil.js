/* Lead Performant — accueil : filtre des programmes « Je suis / Je veux ».
   Sans JavaScript, les cinq programmes restent tous affichés. */
(function () {
  "use strict";
  var box = document.querySelector("[data-filtre]");
  if (!box) return;
  var cards = Array.prototype.slice.call(document.querySelectorAll(".rf-pc[data-profil]"));
  var chips = Array.prototype.slice.call(box.querySelectorAll(".rf-chip"));
  var count = box.querySelector(".rf-count");
  var state = { profil: "", objectif: "" };
  box.hidden = false;

  function apply() {
    var n = 0;
    cards.forEach(function (c) {
      var okP = !state.profil || (" " + c.getAttribute("data-profil") + " ").indexOf(" " + state.profil + " ") > -1;
      var okO = !state.objectif || c.getAttribute("data-objectif") === state.objectif;
      var on = okP && okO;
      c.classList.toggle("is-off", !on);
      c.setAttribute("aria-hidden", on ? "false" : "true");
      c.tabIndex = on ? 0 : -1;
      if (on) n++;
    });
    if (!state.profil && !state.objectif) { count.textContent = ""; return; }
    count.innerHTML = n === 0
      ? 'Aucun programme ne répond exactement à cette combinaison. <a class="link" href="/contact/?besoin=echange">Parlons-en trente minutes</a> <button type="button" class="link rf-reset">Tout afficher</button>'
      : (n === 1 ? "Un programme correspond à votre situation. " : n + " programmes correspondent à votre situation. ") + '<button type="button" class="link rf-reset">Tout afficher</button>';
  }
  function select(f, v) {
    state[f] = v;
    chips.forEach(function (c) { if (c.getAttribute("data-f") === f) c.setAttribute("aria-pressed", c.getAttribute("data-v") === v ? "true" : "false"); });
    apply();
  }
  chips.forEach(function (c) {
    c.addEventListener("click", function () { select(c.getAttribute("data-f"), c.getAttribute("data-v")); });
  });
  box.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("rf-reset")) { select("profil", ""); select("objectif", ""); }
  });
})();
