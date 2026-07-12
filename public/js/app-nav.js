/**
 * Site-wide navigation without full page reloads.
 *
 * Intercepts internal same-origin <a> clicks, fetches the target page,
 * swaps #app-shell's content, re-runs any <script> tags in the new HTML
 * (in order, awaiting external <script src> loads so libraries like
 * Drawflow are ready before their init code runs), and pushes browser
 * history. Falls back to a real navigation on any failure, or when the
 * fetched page has no #app-shell (e.g. session expired -> login page).
 */
(function () {
    'use strict';

    var progressEl = document.getElementById('rx-nav-progress');
    var inFlight = false;

    function showProgress() {
        if (!progressEl) return;
        progressEl.style.transition = 'none';
        progressEl.style.width = '0%';
        progressEl.style.opacity = '1';
        // Force reflow so the transition below actually animates from 0%.
        void progressEl.offsetWidth;
        progressEl.style.transition = 'width 0.4s ease-out';
        progressEl.style.width = '80%';
    }

    function hideProgress(success) {
        if (!progressEl) return;
        progressEl.style.transition = 'width 0.15s ease-out';
        progressEl.style.width = success ? '100%' : '0%';
        setTimeout(function () {
            progressEl.style.transition = 'opacity 0.2s ease-out';
            progressEl.style.opacity = '0';
        }, 150);
    }

    function isNavigable(link) {
        if (!link || !link.href) return false;
        if (link.hasAttribute('data-no-pjax')) return false;
        if (link.target && link.target !== '' && link.target !== '_self') return false;
        if (link.hasAttribute('download')) return false;
        if (link.origin !== window.location.origin) return false;
        var proto = link.protocol;
        if (proto !== 'http:' && proto !== 'https:') return false;
        // Same-page hash link — let the browser handle scrolling natively.
        if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) return false;
        return true;
    }

    async function executeScripts(container) {
        var scripts = Array.prototype.slice.call(container.querySelectorAll('script'));
        for (var i = 0; i < scripts.length; i++) {
            var oldScript = scripts[i];
            var newScript = document.createElement('script');
            for (var j = 0; j < oldScript.attributes.length; j++) {
                var attr = oldScript.attributes[j];
                newScript.setAttribute(attr.name, attr.value);
            }
            if (oldScript.src) {
                await new Promise(function (resolve) {
                    newScript.onload = resolve;
                    newScript.onerror = resolve;
                    oldScript.replaceWith(newScript);
                });
            } else {
                newScript.textContent = oldScript.textContent;
                oldScript.replaceWith(newScript);
            }
        }
    }

    async function navigate(url, push) {
        if (inFlight) return;
        inFlight = true;
        showProgress();

        try {
            var res = await fetch(url, { headers: { 'X-Requested-With': 'RovixNav' } });
            var html = await res.text();
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var newShell = doc.getElementById('app-shell');
            var currentShell = document.getElementById('app-shell');

            if (!res.ok || !newShell || !currentShell) {
                // Not a normal app page (redirected to login, error page,
                // etc.) — do a real navigation so the browser handles it.
                window.location.href = url;
                return;
            }

            document.title = doc.title;
            currentShell.innerHTML = newShell.innerHTML;
            await executeScripts(currentShell);

            if (push) {
                history.pushState({ rxNav: true }, '', url);
            }
            currentShell.scrollTop = 0;
            var main = currentShell.querySelector('main');
            if (main) main.scrollTop = 0;

            hideProgress(true);
        } catch (e) {
            window.location.href = url;
        } finally {
            inFlight = false;
        }
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var link = e.target.closest('a');
        if (!isNavigable(link)) return;
        if (link.href === window.location.href) { e.preventDefault(); return; }

        e.preventDefault();
        navigate(link.href, true);
    });

    window.addEventListener('popstate', function () {
        navigate(window.location.href, false);
    });

    // Public API for pages that currently call window.location.reload()
    // after an AJAX action (send message, confirm, etc.) — swaps in fresh
    // content the same way link navigation does, no full browser reload.
    window.RovixNav = {
        refresh: function () {
            if (inFlight) {
                return new Promise(function (resolve) {
                    setTimeout(function () { resolve(window.RovixNav.refresh()); }, 150);
                });
            }
            return navigate(window.location.href, false);
        },
        to: function (url) { return navigate(url, true); }
    };
})();
