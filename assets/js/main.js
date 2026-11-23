/* session start time (used by the inquiry popup) */
try { if (!sessionStorage.getItem("maritorSessionStart")) sessionStorage.setItem("maritorSessionStart", String(Date.now())); } catch (e) {}

(function () {
  "use strict";
  var doc = document.documentElement;
  doc.classList.remove("no-js");

  /* ---------- Header shadow on scroll ---------- */
  var header = document.querySelector(".site-header");
  function onScroll() { if (header) header.classList.toggle("is-scrolled", window.scrollY > 10); }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile menu ---------- */
  var toggle = document.querySelector(".nav-toggle");
  if (toggle) {
    toggle.addEventListener("click", function () {
      var open = document.body.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });
    document.querySelectorAll(".nav__link").forEach(function (a) {
      a.addEventListener("click", function () { document.body.classList.remove("nav-open"); toggle.setAttribute("aria-expanded", "false"); });
    });
  }

  /* ---------- Hero video: lighter file on small screens, respect reduced motion ---------- */
  var video = document.getElementById("heroVideo");
  if (video) {
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (window.innerWidth < 900 && video.dataset.srcMobile) {
      video.src = video.dataset.srcMobile;
      video.load();
    }
    if (reduce) { video.removeAttribute("autoplay"); video.pause(); }
    else {
      var p = video.play();
      if (p && p.catch) p.catch(function () { /* autoplay blocked: poster stays */ });
    }
  }

  /* ---------- Reveal on scroll ---------- */
  var items = document.querySelectorAll(".reveal, .reveal-img");
  if ("IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("is-visible"); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    items.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 90 + "ms"; io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add("is-visible"); });
  }

  /* ---------- Year ---------- */
  document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* ---------- Contact form ---------- */
  var form = document.getElementById("contactForm");
  if (!form) return;

  var ts = document.getElementById("f-ts");
  if (ts) ts.value = String(Date.now());

  // Preselect service from ?service=...
  var q = new URLSearchParams(location.search).get("service");
  var select = form.querySelector("#f-service");
  if (q && select && select.querySelector('option[value="' + q + '"]')) select.value = q;

  var status = form.querySelector(".form-status");
  var button = form.querySelector('button[type="submit"]');

  var rules = {
    name: function (v) { return v.trim().length >= 2; },
    phone: function (v) { var d = v.replace(/\D/g, ""); return /^[+\d\s().-]{6,30}$/.test(v.trim()) && d.length >= 6 && d.length <= 15; },
    email: function (v) { return v.trim() === "" || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); },
    service: function (v) { return v !== ""; },
    message: function (v) { return v.trim().length >= 5; }
  };

  function check(field) {
    var rule = rules[field.name];
    if (!rule) return true;
    var ok = rule(field.value || "");
    field.closest(".field").classList.toggle("is-invalid", !ok);
    field.setAttribute("aria-invalid", ok ? "false" : "true");
    return ok;
  }

  Object.keys(rules).forEach(function (name) {
    var f = form.elements[name];
    if (!f) return;
    f.addEventListener("blur", function () { check(f); });
    f.addEventListener("input", function () { if (f.closest(".field").classList.contains("is-invalid")) check(f); });
  });

  function setStatus(type, msg) {
    status.className = "form-status is-" + type;
    status.textContent = msg;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var firstBad = null;
    Object.keys(rules).forEach(function (name) {
      var f = form.elements[name];
      if (f && !check(f) && !firstBad) firstBad = f;
    });
    if (firstBad) { firstBad.focus(); return; }

    button.disabled = true;
    var label = button.innerHTML;
    button.textContent = "Sending…";
    status.className = "form-status";

    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { "Accept": "application/json", "X-Requested-With": "fetch" }
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (res) {
        if (res && res.ok) {
          form.reset();
          if (ts) ts.value = String(Date.now());
          setStatus("success", "Thank you. Your message has been sent — our Operations Desk will contact you shortly.");
        } else {
          setStatus("error", (res && res.error) || "Sorry, the message could not be sent. Please try again in a moment.");
        }
      })
      .catch(function () { setStatus("error", "Network error. Please check your connection and try again."); })
      .then(function () { button.disabled = false; button.innerHTML = label; });
  });
})();

/* ---------- Home: scroll / swipe past the end goes to About us ---------- */
(function () {
  if (!document.body.classList.contains("page-home")) return;
  var target = "services.html", busy = false, startY = null;
  function atBottom() { return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2; }
  function go() { if (busy) return; busy = true; document.body.style.transition = "opacity .35s"; document.body.style.opacity = "0"; setTimeout(function () { location.href = target; }, 300); }
  window.addEventListener("wheel", function (e) { if (e.deltaY > 30 && atBottom()) go(); }, { passive: true });
  window.addEventListener("keydown", function (e) { if ((e.key === "ArrowDown" || e.key === "PageDown" || e.key === " ") && atBottom()) go(); });
  window.addEventListener("touchstart", function (e) { startY = e.touches[0].clientY; }, { passive: true });
  window.addEventListener("touchend", function (e) {
    if (startY === null) return;
    var dy = startY - e.changedTouches[0].clientY; startY = null;
    if (dy > 70 && atBottom()) go();
  }, { passive: true });
  window.addEventListener("pageshow", function () { busy = false; document.body.style.opacity = ""; });
})();

/* ---------- Inner pages: floating contact button + inquiry popup ---------- */
(function () {
  var body = document.body;
  if (body.classList.contains("page-home")) return;
  var onContact = /contact\.html$/.test(location.pathname);
  var arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
  if (onContact) return;

  var modal = document.createElement("div");
  modal.className = "modal"; modal.id = "inquiryModal";
  modal.setAttribute("role", "dialog"); modal.setAttribute("aria-modal", "true"); modal.setAttribute("aria-labelledby", "inqTitle");
  modal.innerHTML =
    '<div class="modal__backdrop" data-close></div>' +
    '<div class="modal__dialog">' +
      '<button class="modal__close" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
      '<div class="modal__top">' +
        '<img src="assets/img/logo-white.svg" alt="Maritor">' +
        '<h2 id="inqTitle">How can we help?</h2>' +
        '<p>Send an inquiry to our Operations Desk.</p>' +
        '<span class="ops-dot">Available 24/7/365</span>' +
      '</div>' +
      '<div class="modal__body">' +
        '<ul class="modal__list">' +
          '<li><a href="contact.html?service=chartering">Chartering Inquiry' + arrow + '</a></li>' +
          '<li><a href="contact.html?service=security">Maritime Security' + arrow + '</a></li>' +
          '<li><a href="contact.html?service=insurance">Marine Insurance' + arrow + '</a></li>' +
          '<li><a href="contact.html?service=general">General Inquiry' + arrow + '</a></li>' +
        '</ul>' +
        '<div class="modal__actions"><a class="btn btn--primary" href="contact.html">Contact us ' + arrow + '</a>' +
        '<button class="modal__later" data-close>Maybe later</button></div>' +
      '</div>' +
    '</div>';
  body.appendChild(modal);

  var fab = document.createElement("button");
  fab.className = "fab"; fab.type = "button";
  fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg><span>Send an inquiry</span>';
  body.appendChild(fab);

  var lastFocus = null;
  function open() {
    lastFocus = document.activeElement;
    modal.classList.add("is-open");
    body.style.overflow = "hidden";
    setTimeout(function () { var c = modal.querySelector(".modal__list a"); if (c) c.focus(); }, 50);
    try { sessionStorage.setItem("maritorPopupSeen", "1"); } catch (e) {}
  }
  function close() {
    modal.classList.remove("is-open");
    body.style.overflow = "";
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  modal.addEventListener("click", function (e) { if (e.target.closest("[data-close]")) close(); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && modal.classList.contains("is-open")) close(); });
  fab.addEventListener("click", open);

  setTimeout(function () { fab.classList.add("is-shown"); }, 1200);

  // auto-open once per session after 2 minutes on the site (time counted across pages)
  var seen = false, start = Date.now();
  try {
    seen = sessionStorage.getItem("maritorPopupSeen") === "1";
    start = parseInt(sessionStorage.getItem("maritorSessionStart"), 10) || Date.now();
    sessionStorage.setItem("maritorSessionStart", String(start));
  } catch (e) {}
  if (!seen) {
    var wait = Math.max(0, 120000 - (Date.now() - start));
    setTimeout(function () { if (!modal.classList.contains("is-open")) open(); }, wait);
  }
})();
