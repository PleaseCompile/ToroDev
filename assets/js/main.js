/* ==========================================================================
   ToroDev — site behaviour
   Vanilla JS, no dependencies. Progressive enhancement: everything still
   works (readable + submittable) with JS disabled.
   ========================================================================== */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- header: solid background once scrolled ---------- */
  var hdr = document.querySelector('.hdr');
  if (hdr) {
    var onScroll = function () {
      hdr.classList.toggle('is-stuck', window.scrollY > 16);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- mobile drawer ---------- */
  var burger = document.querySelector('.burger');
  var drawer = document.getElementById('drawer');
  if (burger && drawer) {
    var setDrawer = function (open) {
      burger.setAttribute('aria-expanded', String(open));
      drawer.classList.toggle('is-open', open);
      drawer.setAttribute('aria-hidden', String(!open));
      document.body.classList.toggle('is-locked', open);
    };
    burger.addEventListener('click', function () {
      setDrawer(burger.getAttribute('aria-expanded') !== 'true');
    });
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('a')) setDrawer(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
        setDrawer(false);
        burger.focus();
      }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1040 && drawer.classList.contains('is-open')) setDrawer(false);
    });
  }

  /* ---------- reveal on scroll ---------- */
  var revealables = document.querySelectorAll('.rv');
  if (revealables.length) {
    if (reduced || !('IntersectionObserver' in window)) {
      revealables.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
          }
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
      revealables.forEach(function (el) { io.observe(el); });
    }
  }

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('.faq-q').forEach(function (btn) {
    var panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (!panel) return;
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      // close siblings inside the same .faq group
      var group = btn.closest('.faq');
      if (group && !open) {
        group.querySelectorAll('.faq-q[aria-expanded="true"]').forEach(function (other) {
          var otherPanel = document.getElementById(other.getAttribute('aria-controls'));
          other.setAttribute('aria-expanded', 'false');
          if (otherPanel) otherPanel.style.height = '0px';
        });
      }
      btn.setAttribute('aria-expanded', String(!open));
      panel.style.height = open ? '0px' : panel.scrollHeight + 'px';
    });
  });
  // keep an open panel correctly sized after a resize / font swap
  window.addEventListener('resize', function () {
    document.querySelectorAll('.faq-q[aria-expanded="true"]').forEach(function (btn) {
      var panel = document.getElementById(btn.getAttribute('aria-controls'));
      if (panel) panel.style.height = panel.scrollHeight + 'px';
    });
  });

  /* ---------- pricing tabs ---------- */
  document.querySelectorAll('[data-tabs]').forEach(function (group) {
    var tabs = group.querySelectorAll('.tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('is-on');
          t.setAttribute('aria-selected', 'false');
          var p = document.getElementById(t.getAttribute('aria-controls'));
          if (p) p.hidden = true;
        });
        tab.classList.add('is-on');
        tab.setAttribute('aria-selected', 'true');
        var panel = document.getElementById(tab.getAttribute('aria-controls'));
        if (panel) panel.hidden = false;
      });
      tab.addEventListener('keydown', function (e) {
        var list = Array.prototype.slice.call(tabs);
        var i = list.indexOf(tab);
        var next = e.key === 'ArrowRight' ? list[i + 1] : e.key === 'ArrowLeft' ? list[i - 1] : null;
        if (next) { e.preventDefault(); next.focus(); next.click(); }
      });
    });

    /* deep link: /pricing.html#line must open the tab that holds #line */
    var openFromHash = function () {
      var id = window.location.hash.slice(1);
      if (!id) return;
      var target = document.getElementById(id);
      if (!target) return;
      var panel = target.closest('.tabpanel');
      if (!panel) return;
      var tab = group.querySelector('[aria-controls="' + panel.id + '"]');
      if (!tab || tab.classList.contains('is-on')) return;
      tab.click();
      // the panel was display:none when the browser tried to jump — scroll now
      window.requestAnimationFrame(function () {
        target.scrollIntoView({ behavior: 'auto', block: 'start' });
      });
    };
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
  });

  /* ---------- contact form: client-side validation ---------- */
  var form = document.querySelector('form[data-validate]');
  if (form) {
    var showError = function (field, message) {
      field.classList.add('has-err');
      var slot = field.querySelector('.field-err');
      if (slot) slot.textContent = message;
      var input = field.querySelector('input,textarea,select');
      if (input) input.setAttribute('aria-invalid', 'true');
    };
    var clearError = function (field) {
      field.classList.remove('has-err');
      var input = field.querySelector('input,textarea,select');
      if (input) input.removeAttribute('aria-invalid');
    };

    form.addEventListener('submit', function (e) {
      var firstBad = null;

      form.querySelectorAll('.field').forEach(function (field) {
        var input = field.querySelector('input,textarea,select');
        if (!input || !input.required) return;
        clearError(field);

        var value = (input.value || '').trim();
        if (!value) {
          showError(field, input.tagName === 'SELECT' ? 'กรุณาเลือกจากรายการ' : 'กรุณากรอกข้อมูลในช่องนี้');
          firstBad = firstBad || input;
          return;
        }
        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
          showError(field, 'รูปแบบอีเมลไม่ถูกต้อง');
          firstBad = firstBad || input;
          return;
        }
        if (input.type === 'tel' && value.replace(/\D/g, '').length < 9) {
          showError(field, 'กรุณากรอกเบอร์โทรให้ครบถ้วน');
          firstBad = firstBad || input;
        }
      });

      var consent = form.querySelector('.consent input[required]');
      var consentWrap = consent && consent.closest('.consent');
      if (consentWrap) consentWrap.classList.remove('is-bad');
      if (consent && !consent.checked) {
        if (consentWrap) consentWrap.classList.add('is-bad');
        firstBad = firstBad || consent;
      }

      if (firstBad) {
        e.preventDefault();
        firstBad.focus();
        firstBad.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
      }
    });

    form.querySelectorAll('input,textarea,select').forEach(function (input) {
      input.addEventListener('input', function () {
        var field = input.closest('.field');
        if (field && field.classList.contains('has-err')) clearError(field);
        var wrap = input.closest('.consent');
        if (wrap) wrap.classList.remove('is-bad');
      });
      input.addEventListener('change', function () {
        var wrap = input.closest('.consent');
        if (wrap) wrap.classList.remove('is-bad');
      });
    });

    /* prefill "บริการที่สนใจ" from a ?service= query string, e.g. /contact.html?service=line */
    var wanted = new URLSearchParams(window.location.search).get('service');
    var select = form.querySelector('select[name="service"]');
    if (wanted && select) {
      Array.prototype.forEach.call(select.options, function (opt) {
        if (opt.value === wanted) select.value = wanted;
      });
    }
  }

  /* ---------- form result banner (?sent=… set by contact.php) ---------- */
  var statusSlot = document.getElementById('formStatus');
  if (statusSlot) {
    var MESSAGES = {
      ok: ['ok', 'ส่งข้อมูลเรียบร้อยแล้ว ขอบคุณครับ — เราจะติดต่อกลับภายใน 1 วันทำการ ถ้าเร่งด่วนทักทาง LINE ได้เลย'],
      failed: ['no', 'ส่งไม่สำเร็จ ระบบอีเมลมีปัญหาชั่วคราว รบกวนทักมาทาง LINE @torodev หรือส่งอีเมลหาเราโดยตรงแทน'],
      invalid: ['no', 'ข้อมูลไม่ครบหรือรูปแบบไม่ถูกต้อง กรุณาตรวจสอบแล้วส่งใหม่อีกครั้ง'],
      throttled: ['no', 'คุณเพิ่งส่งข้อมูลไปเมื่อครู่ กรุณารอสักครู่แล้วลองใหม่อีกครั้ง']
    };
    var result = MESSAGES[new URLSearchParams(window.location.search).get('sent')];
    if (result) {
      var box = document.createElement('div');
      box.className = 'alert alert--' + result[0];
      box.style.marginBottom = '26px';
      box.textContent = result[1];
      statusSlot.appendChild(box);
      statusSlot.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
      // drop the query string so a refresh doesn't re-show the banner
      if (window.history.replaceState) {
        window.history.replaceState({}, '', window.location.pathname);
      }
    }
  }

  /* ---------- footer year ---------- */
  document.querySelectorAll('[data-year]').forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });
})();
