// assets/js/csrf.js
// Adds the CSRF token (from <meta name="csrf-token">) to every same-origin fetch() that is not a GET/HEAD.
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    if (!token || !window.fetch) return;

    var nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        var method = (init.method || (input && input.method) || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var sameOrigin = url.charAt(0) === '/' || url.indexOf(location.origin) === 0 || !/^[a-z]+:/i.test(url);
            if (sameOrigin) {
                var headers = new Headers(init.headers || (input && input.headers) || {});
                headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
        }
        return nativeFetch(input, init);
    };
})();
