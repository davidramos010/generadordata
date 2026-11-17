(function () {
    var KEY = 'gd-theme';
    var root = document.documentElement;

    function apply(theme) {
        root.setAttribute('data-bs-theme', theme);
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            apply(next);
            try { localStorage.setItem(KEY, next); } catch (e) {}
        });
    });
})();
