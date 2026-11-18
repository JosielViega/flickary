(function () {
    'use strict';

    if (typeof window.fetch !== 'function' || typeof window.history.pushState !== 'function'
        || typeof window.URL !== 'function' || typeof window.DOMParser !== 'function') {
        return;
    }

    var CACHE_TTL_MS = 30000;
    var CACHE_LIMIT = 20;
    var PROGRESS_DELAY_MS = 140;
    var cache = new Map();
    var sequence = 0;
    var activeNavigation = null;
    var parser = new DOMParser();
    var main = document.getElementById('conteudo-principal');
    var progress = document.querySelector('.navigation-progress');

    if (!main) {
        return;
    }

    function now() {
        return window.performance && typeof window.performance.now === 'function'
            ? window.performance.now()
            : Date.now();
    }

    function cacheGet(key) {
        var entry = cache.get(key);
        if (!entry) {
            return null;
        }
        if (entry.expiresAt <= Date.now()) {
            cache.delete(key);
            return null;
        }
        cache.delete(key);
        cache.set(key, entry);
        return entry.payload;
    }

    function cachePut(key, payload) {
        cache.delete(key);
        cache.set(key, { expiresAt: Date.now() + CACHE_TTL_MS, payload: payload });
        while (cache.size > CACHE_LIMIT) {
            cache.delete(cache.keys().next().value);
        }
    }

    function rememberScroll() {
        var state = history.state && history.state.flickary ? history.state : {};
        history.replaceState({ flickary: true, url: location.href, scrollY: window.scrollY }, '', location.href);
        return state;
    }

    function startProgress() {
        var shown = false;
        var timer = window.setTimeout(function () {
            shown = true;
            if (progress) {
                progress.classList.remove('is-complete');
                progress.classList.add('is-visible');
            }
        }, PROGRESS_DELAY_MS);
        return function () {
            window.clearTimeout(timer);
            if (!progress || !shown) {
                return;
            }
            progress.classList.add('is-complete');
            window.setTimeout(function () {
                progress.classList.remove('is-visible', 'is-complete');
            }, 150);
        };
    }

    function parsePartial(html) {
        var parsed = parser.parseFromString(html, 'text/html');
        var envelope = parsed.querySelector('[data-flickary-partial]');
        var content = envelope && envelope.querySelector('[data-flickary-content]');
        if (!envelope || !content) {
            return null;
        }
        var headTemplate = parsed.querySelector('template[data-flickary-head]');
        return {
            title: envelope.dataset.title || 'Flickary',
            currentRoute: envelope.dataset.currentRoute || '',
            searchQuery: envelope.dataset.searchQuery || '',
            cacheable: envelope.dataset.cacheable === '1',
            content: content.innerHTML,
            head: headTemplate ? headTemplate.innerHTML : ''
        };
    }

    function updateHead(payload) {
        document.querySelectorAll('[data-flickary-page-head]').forEach(function (element) {
            element.remove();
        });
        if (payload.head === '') {
            return;
        }
        var parsed = parser.parseFromString('<template data-head>' + payload.head + '</template>', 'text/html');
        var template = parsed.querySelector('template[data-head]');
        if (!template) {
            return;
        }
        Array.prototype.forEach.call(template.content.children, function (element) {
            document.head.appendChild(element.cloneNode(true));
        });
    }

    function updateNavigation(currentRoute) {
        document.querySelectorAll('[data-nav-route]').forEach(function (link) {
            var routes = (link.dataset.navRoute || '').split(/\s+/).filter(Boolean);
            var active = currentRoute !== '' && routes.indexOf(currentRoute) !== -1;
            link.classList.toggle('is-active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function restoreScroll(url, scrollY) {
        if (url.hash) {
            var id;
            try {
                id = decodeURIComponent(url.hash.slice(1));
            } catch (error) {
                id = url.hash.slice(1);
            }
            var target = document.getElementById(id);
            if (target) {
                target.scrollIntoView();
                return;
            }
        }
        window.scrollTo(0, typeof scrollY === 'number' ? scrollY : 0);
    }

    function render(payload, url, options, startedAt, fromCache) {
        main.innerHTML = payload.content;
        document.title = payload.title;
        updateHead(payload);
        updateNavigation(payload.currentRoute);
        var topbarSearch = document.getElementById('topbar-search');
        if (topbarSearch) {
            topbarSearch.value = payload.searchQuery;
        }

        if (options.historyMode === 'push') {
            history.pushState({ flickary: true, url: url.href, scrollY: 0 }, '', url.href);
        }
        if (options.focus) {
            try {
                main.focus({ preventScroll: true });
            } catch (error) {
                main.focus();
            }
        }
        restoreScroll(url, options.scrollY);

        document.dispatchEvent(new CustomEvent('flickary:navigated', {
            detail: { url: url.href, fromCache: fromCache, durationMs: Math.round((now() - startedAt) * 10) / 10 }
        }));
    }

    function fetchPartial(url, controller) {
        return fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Flickary-Navigation': 'partial', 'Accept': 'text/html' },
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            if (response.redirected) {
                return { redirect: response.url };
            }
            if (response.status >= 500) {
                throw new Error('server-response');
            }
            return response.text().then(function (html) {
                return { payload: parsePartial(html) };
            });
        });
    }

    function navigate(input, options) {
        options = options || {};
        var url = input instanceof URL ? input : new URL(input, location.href);
        var key = url.href;
        if (activeNavigation && activeNavigation.url === key) {
            return activeNavigation.promise;
        }
        if (activeNavigation && activeNavigation.controller) {
            activeNavigation.controller.abort();
        }

        if (options.historyMode === 'push') {
            rememberScroll();
        }
        var navigationSequence = ++sequence;
        var startedAt = now();
        var cached = cacheGet(key);
        if (cached) {
            render(cached, url, options, startedAt, true);
            return Promise.resolve();
        }

        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var finishProgress = startProgress();
        var promise = fetchPartial(url, controller).then(function (result) {
            if (navigationSequence !== sequence) {
                return;
            }
            if (result.redirect) {
                window.location.assign(result.redirect);
                return;
            }
            if (!result.payload) {
                window.location.assign(url.href);
                return;
            }
            if (result.payload.cacheable) {
                cachePut(key, result.payload);
            }
            render(result.payload, url, options, startedAt, false);
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            if (navigationSequence === sequence) {
                window.location.assign(url.href);
            }
        }).finally(function () {
            finishProgress();
            if (activeNavigation && activeNavigation.sequence === navigationSequence) {
                activeNavigation = null;
            }
        });

        activeNavigation = { url: key, sequence: navigationSequence, controller: controller, promise: promise };
        return promise;
    }

    function eligibleLink(event, anchor) {
        if (!anchor || event.button !== 0 || event.ctrlKey || event.shiftKey || event.altKey || event.metaKey
            || anchor.hasAttribute('download') || anchor.hasAttribute('data-no-partial')) {
            return null;
        }
        var target = (anchor.getAttribute('target') || '').toLowerCase();
        if (target !== '' && target !== '_self') {
            return null;
        }
        var url;
        try {
            url = new URL(anchor.href, location.href);
        } catch (error) {
            return null;
        }
        if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.origin !== location.origin) {
            return null;
        }
        if (url.pathname === location.pathname && url.search === location.search && url.hash !== location.hash) {
            return null;
        }
        return url;
    }

    document.addEventListener('click', function (event) {
        var anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
        var url = eligibleLink(event, anchor);
        if (!url) {
            return;
        }
        event.preventDefault();
        navigate(url, { historyMode: 'push', focus: true });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-partial-get')
            || form.method.toUpperCase() !== 'GET') {
            return;
        }
        event.preventDefault();
        var url = new URL(form.action || location.href, location.href);
        url.search = '';
        new FormData(form).forEach(function (value, name) {
            if (typeof value === 'string') {
                url.searchParams.append(name, value);
            }
        });
        navigate(url, { historyMode: 'push', focus: true });
    });

    window.addEventListener('popstate', function (event) {
        var state = event.state;
        if (!state || state.flickary !== true) {
            window.location.assign(location.href);
            return;
        }
        navigate(new URL(state.url || location.href, location.href), {
            historyMode: 'pop', focus: false, scrollY: typeof state.scrollY === 'number' ? state.scrollY : 0
        });
    });

    window.addEventListener('pagehide', rememberScroll);
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    history.replaceState({ flickary: true, url: location.href, scrollY: window.scrollY }, '', location.href);
    window.FlickaryNavigation = {
        navigate: function (url) { return navigate(url, { historyMode: 'push', focus: true }); },
        cacheSize: function () { return cache.size; }
    };
}());
