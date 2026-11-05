/*
 * CZ Outline — pannello indice.
 *
 * L'indice generato da [outline] diventa un pannello a sinistra, aperto e
 * chiuso da un pulsante:
 * - desktop (>= 1024px): barra laterale sotto la barra del tema; quando è
 *   aperta il contenuto si sposta a destra (body.cz-outline-sidebar-open).
 *   Parte chiusa e la scelta del lettore viene ricordata (localStorage).
 * - schermi stretti: drawer sopra il contenuto con sfondo oscurato.
 * In entrambi la sezione corrente è evidenziata (.active) e mantenuta
 * visibile nel pannello, che scorre per conto suo.
 *
 * Pulsanti di apertura (creati qui): "indice" nel testo, al posto del
 * blocco indice, e un'icona nella czcr-toolbar (plugin cz-continue-reading)
 * a sinistra di "Su". Funziona anche qualunque altro [data-cz-outline-toggle]
 * (etichette in data-label-show / data-label-hide).
 */
(function () {
  "use strict";

  var DESKTOP_QUERY = "(min-width: 1024px)";
  var STORAGE_KEY = "cz_outline_sidebar_open";
  var ACTIVE_LINE = 0.25; // sezione corrente: l'ultimo titolo sopra il 25% della finestra
  var BASE_OFFSET = typeof window.czOutlineOffset === "number" ? window.czOutlineOffset : 0;
  // Icona "indice" (tre righe a gradini), usata da entrambi i pulsanti.
  var ICON =
    '<svg class="cz-outline-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" ' +
    'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">' +
    '<path d="M4 7h16M7 12h13M10 17h10"/></svg>';

  function getAdminBarOffset() {
    var adminBar = document.getElementById("wpadminbar");
    if (!adminBar) return 0;
    var style = window.getComputedStyle(adminBar);
    if (!style || style.display === "none" || style.visibility === "hidden") return 0;
    if (style.position !== "fixed") return 0;
    var h = adminBar.getBoundingClientRect().height || adminBar.offsetHeight || 0;
    return h > 0 ? Math.round(h) : 0;
  }

  function getEffectiveOffset() {
    return BASE_OFFSET + getAdminBarOffset();
  }

  // Bordo inferiore della barra di navigazione del tema, se visibile in alto.
  function getTopBarBottom() {
    var bottom = getAdminBarOffset();
    var bar = document.querySelector(".top-nav-bar");
    if (bar) {
      var r = bar.getBoundingClientRect();
      if (r.bottom > bottom) bottom = r.bottom;
    }
    return Math.max(0, Math.round(bottom));
  }

  function readStoredOpen() {
    try {
      return window.localStorage.getItem(STORAGE_KEY) === "1";
    } catch (e) {
      return false;
    }
  }

  function storeOpen(open) {
    try {
      window.localStorage.setItem(STORAGE_KEY, open ? "1" : "0");
    } catch (e) {}
  }

  function decodeHash(hash) {
    try {
      return decodeURIComponent((hash || "").replace(/^#/, ""));
    } catch (e) {
      return (hash || "").replace(/^#/, "");
    }
  }

  function samePage(url) {
    return (
      url.origin === window.location.origin &&
      url.pathname === window.location.pathname &&
      url.search === window.location.search
    );
  }

  function smoothScrollToId(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var top = el.getBoundingClientRect().top + window.pageYOffset - getEffectiveOffset();
    window.scrollTo({ top: top < 0 ? 0 : top, behavior: "smooth" });
    if (window.history && window.history.replaceState) {
      window.history.replaceState(
        null,
        "",
        window.location.pathname + window.location.search + "#" + encodeURIComponent(id),
      );
    }
  }

  function getLinkTargetId(link) {
    if (!link || !link.getAttribute) return "";
    var href = link.getAttribute("href") || "";
    if (href.charAt(0) === "#") return decodeHash(href);
    try {
      var url = new URL(link.href, window.location.href);
      if (samePage(url) && url.hash) return decodeHash(url.hash);
    } catch (e) {}
    return "";
  }

  function initNav(nav) {
    var links = Array.prototype.slice.call(nav.querySelectorAll("a[href]"));
    if (!links.length) return false;

    links.forEach(function (link) {
      var targetId = getLinkTargetId(link);
      if (targetId) link.dataset.czTarget = targetId;
    });
    var localLinks = links.filter(function (link) {
      return !!link.dataset.czTarget;
    });

    var desktopMedia = window.matchMedia(DESKTOP_QUERY);
    var desktopOpen = readStoredOpen();
    var mobileOpen = false;
    var activeId = "";

    if (!nav.id) nav.id = "cz-outline-panel";

    // Pulsanti di apertura:
    // - "indice" nel testo, al posto del blocco indice;
    // - icona nella czcr-toolbar (plugin cz-continue-reading), a sinistra di "Su";
    // - eventuali altri [data-cz-outline-toggle] forniti dal tema.
    var inline = document.createElement("button");
    inline.type = "button";
    inline.className = "cz-outline-toggle";
    inline.setAttribute("data-cz-outline-toggle", "");
    inline.setAttribute("data-label-show", "indice");
    inline.setAttribute("data-label-hide", "nascondi indice");
    inline.innerHTML =
      ICON + '<span class="cz-outline-toggle-label" data-cz-outline-toggle-label>indice</span>';
    nav.parentNode.insertBefore(inline, nav);

    var czcrToolbar = document.querySelector(".czcr-toolbar");
    if (czcrToolbar) {
      var tbBtn = document.createElement("button");
      tbBtn.type = "button";
      tbBtn.className = "czcr-toolbar__btn cz-outline-czcr-btn";
      tbBtn.setAttribute("data-cz-outline-toggle", "");
      tbBtn.setAttribute("data-label-show", "Mostra indice");
      tbBtn.setAttribute("data-label-hide", "Nascondi indice");
      tbBtn.innerHTML = '<span class="czcr-icon" aria-hidden="true">' + ICON + "</span>";
      var topBtn = czcrToolbar.querySelector("[data-czcr-top]");
      var group = czcrToolbar.querySelector(".czcr-toolbar__group");
      if (topBtn) topBtn.parentNode.insertBefore(tbBtn, topBtn);
      else if (group) group.insertBefore(tbBtn, group.firstChild);
      else czcrToolbar.appendChild(tbBtn);
    }

    var toggles = Array.prototype.slice.call(document.querySelectorAll("[data-cz-outline-toggle]"));
    toggles.forEach(function (btn) {
      btn.setAttribute("aria-controls", nav.id);
    });

    // Il pannello vive in <body>: un antenato con filter/transform (es.
    // #primary quando il menu del tema è aperto) sposterebbe un elemento fixed.
    document.body.appendChild(nav);
    nav.classList.add("cz-outline--panel");

    var backdrop = document.createElement("div");
    backdrop.className = "cz-outline-backdrop";
    backdrop.setAttribute("aria-hidden", "true");
    document.body.appendChild(backdrop);

    function isDesktop() {
      return desktopMedia.matches;
    }

    function isOpen() {
      return isDesktop() ? desktopOpen : mobileOpen;
    }

    function updateTop() {
      nav.style.setProperty("--cz-outline-top", (isDesktop() ? getTopBarBottom() : 0) + "px");
    }

    function render() {
      var desktop = isDesktop();
      var open = isOpen();

      nav.classList.toggle("cz-outline--sidebar", desktop);
      nav.classList.toggle("cz-outline--drawer", !desktop);
      nav.classList.toggle("is-open", open);
      backdrop.classList.toggle("is-visible", !desktop && open);
      document.body.classList.toggle("cz-outline-sidebar-open", desktop && open);
      document.body.classList.toggle("cz-outline-mobile-open", !desktop && open);

      toggles.forEach(function (btn) {
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        var label = btn.querySelector("[data-cz-outline-toggle-label]");
        var text = btn.getAttribute(open ? "data-label-hide" : "data-label-show");
        if (!text) return;
        if (label) {
          label.textContent = text;
        } else {
          // Pulsante con sola icona: il testo va in aria-label e title.
          btn.setAttribute("aria-label", text);
          btn.setAttribute("title", text);
        }
      });

      updateTop();
      if (open) revealActiveLink(false);
    }

    function setOpen(open) {
      if (isDesktop()) {
        desktopOpen = open;
        storeOpen(open);
      } else {
        mobileOpen = open;
      }
      render();
    }

    toggles.forEach(function (btn) {
      btn.addEventListener("click", function () {
        setOpen(!isOpen());
      });
    });

    var closeButton = nav.querySelector(".cz-outline-close");
    if (closeButton) {
      closeButton.addEventListener("click", function () {
        setOpen(false);
      });
    }

    backdrop.addEventListener("click", function () {
      setOpen(false);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !isDesktop() && mobileOpen) setOpen(false);
    });

    if (localLinks.length) {
      nav.addEventListener("click", function (e) {
        var link = e.target.closest("a[href]");
        if (!link || !nav.contains(link)) return;
        var id = link.dataset.czTarget || "";
        if (!id) return;
        e.preventDefault();
        if (!isDesktop()) setOpen(false);
        smoothScrollToId(id);
      });
    }

    // ---------- Sezione corrente ----------

    var targets = [];
    localLinks.forEach(function (link) {
      var id = link.dataset.czTarget;
      var el = document.getElementById(id);
      if (el && targets.every(function (t) { return t.id !== id; })) {
        targets.push({ id: id, el: el });
      }
    });
    targets.sort(function (a, b) {
      return a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
    });

    // Tiene visibile il link attivo dentro il pannello, senza muovere la pagina.
    function revealActiveLink(smooth) {
      if (!activeId || !isOpen()) return;
      var link = nav.querySelector("a.active");
      if (!link) return;
      var navRect = nav.getBoundingClientRect();
      var linkRect = link.getBoundingClientRect();
      var margin = navRect.height * 0.2;
      if (linkRect.top >= navRect.top + margin && linkRect.bottom <= navRect.bottom - margin) return;
      var top = nav.scrollTop + (linkRect.top - navRect.top) - navRect.height / 2 + linkRect.height / 2;
      nav.scrollTo({ top: Math.max(0, top), behavior: smooth ? "smooth" : "auto" });
    }

    function setActive(id) {
      if (id === activeId) return;
      activeId = id;
      localLinks.forEach(function (link) {
        var on = link.dataset.czTarget === id;
        link.classList.toggle("active", on);
        if (on) link.setAttribute("aria-current", "location");
        else link.removeAttribute("aria-current");
      });
      revealActiveLink(true);
    }

    function computeActive() {
      if (!targets.length) return;
      var line = getEffectiveOffset() + window.innerHeight * ACTIVE_LINE;
      var current = "";
      for (var i = 0; i < targets.length; i++) {
        if (targets[i].el.getBoundingClientRect().top <= line) current = targets[i].id;
        else break;
      }
      // In fondo alla pagina gli ultimi titoli non arrivano mai alla linea.
      var atBottom =
        window.innerHeight + window.pageYOffset >= document.documentElement.scrollHeight - 2;
      if (atBottom) {
        for (var j = targets.length - 1; j >= 0; j--) {
          if (targets[j].el.getBoundingClientRect().top < window.innerHeight) {
            current = targets[j].id;
            break;
          }
        }
      }
      setActive(current);
    }

    var ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        updateTop();
        computeActive();
      });
    }

    // La barra del tema si nasconde/mostra con una transizione: riallinea il pannello alla fine.
    var topBar = document.querySelector(".top-nav-bar");
    if (topBar) topBar.addEventListener("transitionend", updateTop);

    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onScroll, { passive: true });
    if (desktopMedia.addEventListener) desktopMedia.addEventListener("change", render);
    else if (desktopMedia.addListener) desktopMedia.addListener(render);

    render();
    computeActive();
    // Animazioni attive solo dopo il primo render (niente slide al caricamento).
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        nav.classList.add("cz-outline--animate");
        document.body.classList.add("cz-outline-animate");
      });
    });
    return true;
  }

  document.addEventListener("DOMContentLoaded", function () {
    var nav = document.querySelector(".cz-outline");
    var ready = nav ? initNav(nav) : false;
    // Nessun indice utilizzabile: niente pulsanti che non aprono nulla.
    if (!ready) {
      document.querySelectorAll("[data-cz-outline-toggle]").forEach(function (btn) {
        btn.hidden = true;
      });
      document.documentElement.classList.remove("cz-outline-js");
    }
  });
})();
