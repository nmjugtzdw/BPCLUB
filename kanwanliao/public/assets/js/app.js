(function () {
  'use strict';

  var STORAGE_KEY = 'kwl-theme';
  var root = document.documentElement;

  function apply(theme) {
    root.setAttribute('data-theme', theme);
  }

  function current() {
    var saved = null;
    try {
      saved = window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      saved = null;
    }
    if (saved === 'light' || saved === 'dark') {
      return saved;
    }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark'
      : 'light';
  }

  apply(current());

  var toggle = document.querySelector('[data-theme-toggle]');
  if (!toggle) {
    return;
  }

  toggle.addEventListener('click', function () {
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    apply(next);
    try {
      window.localStorage.setItem(STORAGE_KEY, next);
    } catch (e) {
      // Storage may be blocked; the theme still applies for this page view.
    }
  });
})();
