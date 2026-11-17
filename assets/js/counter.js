(function () {
    var ruta = location.pathname.replace(/^\/+|\/+$/g, '').replace(/\.html$/, '');
    var pagina = ruta === '' ? 'index' : ruta;
    var clave = 'gd-visita-' + pagina;

    // Una visita por página y sesión: las recargas no vuelven a contar.
    try {
        if (sessionStorage.getItem(clave)) return;
        sessionStorage.setItem(clave, '1');
    } catch (e) {}

    var cuerpo = JSON.stringify({ page: pagina });
    if (navigator.sendBeacon) {
        navigator.sendBeacon('/counter.php', new Blob([cuerpo], { type: 'application/json' }));
    } else {
        fetch('/counter.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: cuerpo, keepalive: true });
    }
})();
