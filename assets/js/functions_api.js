$(document).ready(function() {

    // ── Configuración API base URL (usada solo para mostrar en curl) ────
    var API_BASE_URL = 'http://localhost:8001/api';

    // ── Tema light / dark ─────────────────────────────────────
    var savedTheme = localStorage.getItem('gd-theme');
    if (savedTheme !== 'light') {
        document.body.classList.add('dark-mode');
        document.getElementById('themeIcon').textContent = '☀️';
    }

    $('#themeToggle').click(function() {
        var isDark = document.body.classList.toggle('dark-mode');
        document.getElementById('themeIcon').textContent = isDark ? '☀️' : '🌙';
        localStorage.setItem('gd-theme', isDark ? 'dark' : 'light');
    });

    // ── Estado ────────────────────────────────────────────────
    var tipoActivo = 'DNI';

    // ── Mapa de endpoints por tipo ────────────────────────────
    function getEndpoint(tipo) {
        var map = {
            'DNI': 'generate-dni',
            'NIF': 'generate-nif',
            'NIE': 'generate-nie',
            'CIF': 'generate-cif-by-type'
        };
        return map[tipo] || 'generate-dni';
    }

    // ── Construir URL completa de la API ──────────────────────
    function buildApiUrl(tipo, cantidad, tipoCif) {
        var endpoint = getEndpoint(tipo);
        var url = API_BASE_URL + '/' + endpoint + '?result=' + cantidad;
        if (tipo === 'CIF') {
            url += '&type=' + (tipoCif || 'B');
        }
        return url;
    }

    // ── Generar texto curl con syntax highlighting ────────────
    function buildCurlHtml(tipo, cantidad, tipoCif) {
        var url = buildApiUrl(tipo, cantidad, tipoCif);
        var html = '<span class="curl-kw">curl</span> <span class="curl-flag">--request</span> <span class="curl-val">GET</span> \\\n';
        html += '  <span class="curl-flag">--get</span> <span class="curl-url">"' + url + '"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Authorization: Bearer {YOUR_AUTH_KEY}"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Content-Type: application/json"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Accept: application/json"</span>';
        return html;
    }

    // ── Generar texto curl plano (para copiar) ────────────────
    function buildCurlText(tipo, cantidad, tipoCif) {
        var url = buildApiUrl(tipo, cantidad, tipoCif);
        var text = 'curl --request GET \\\n';
        text += '  --get "' + url + '" \\\n';
        text += '  --header "Authorization: Bearer {YOUR_AUTH_KEY}" \\\n';
        text += '  --header "Content-Type: application/json" \\\n';
        text += '  --header "Accept: application/json"';
        return text;
    }

    // ── Actualizar display del curl ───────────────────────────
    function actualizarCurl() {
        var cantidad = $('#selectCantidad').val();
        var tipoCif = $('#selectTipoCif').val();
        $('#curlDisplay').html(buildCurlHtml(tipoActivo, cantidad, tipoCif));
    }

    // ── Mostrar/ocultar selector CIF ─────────────────────────
    function toggleCifSelector() {
        if (tipoActivo === 'CIF') {
            $('#cifTypeRow').addClass('visible');
        } else {
            $('#cifTypeRow').removeClass('visible');
        }
    }

    // ── Inicialización ───────────────────────────────────────
    actualizarCurl();
    toggleCifSelector();

    // ── Tabs ─────────────────────────────────────────────────
    $('.gd-tab').click(function() {
        tipoActivo = $(this).data('tipo');
        $('.gd-tab').removeClass('active');
        $(this).addClass('active');
        toggleCifSelector();
        actualizarCurl();
    });

    // ── Cambio de cantidad ───────────────────────────────────
    $('#selectCantidad').change(function() {
        actualizarCurl();
    });

    // ── Cambio de tipo CIF ───────────────────────────────────
    $('#selectTipoCif').change(function() {
        actualizarCurl();
    });

    // ── Generar (llamada AJAX al proxy PHP) ──────────────────
    $('#btnGenerar').click(function() {
        var cantidad = $('#selectCantidad').val();
        var tipoCif = $('#selectTipoCif').val();
        var btn = $(this);

        btn.prop('disabled', true).text('⏳ Generando...');

        $.ajax({
            url: 'api_proxy.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                tipo: tipoActivo,
                cantidad: parseInt(cantidad),
                tipoCif: tipoActivo === 'CIF' ? tipoCif : null
            }),
            dataType: 'json',
            success: function(response) {
                btn.prop('disabled', false).text('✨ Generar');

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                var datos = response.data || response;
                if (!Array.isArray(datos)) {
                    datos = [datos];
                }

                $('#historialVacio').hide();
                for (var i = 0; i < datos.length; i++) {
                    var item = $('<li></li>');
                    item.append('<span class="gd-badge">' + tipoActivo + '</span>');
                    item.append('<span class="gd-value">' + datos[i] + '</span>');
                    $('#ulHistorial').prepend(item);
                }
                $('#ulHistorial li:gt(1000)').remove();
            },
            error: function(xhr) {
                btn.prop('disabled', false).text('✨ Generar');
                var msg = 'Error al conectar con la API';
                try {
                    var err = JSON.parse(xhr.responseText);
                    msg = err.error || err.message || msg;
                } catch(e) {}
                alert(msg);
            }
        });
    });

    // ── Copiar curl ──────────────────────────────────────────
    $('#btnCopiarCurl').click(function() {
        var cantidad = $('#selectCantidad').val();
        var tipoCif = $('#selectTipoCif').val();
        var curlText = buildCurlText(tipoActivo, cantidad, tipoCif);

        if (navigator.clipboard) {
            navigator.clipboard.writeText(curlText);
        } else {
            var t = $('<textarea>').val(curlText).appendTo('body');
            t[0].select();
            document.execCommand('copy');
            t.remove();
        }
        var a = $('#alertCopiItem');
        a.fadeIn(200).delay(1800).fadeOut(400);
    });

    // ── Exportar historial ───────────────────────────────────
    $('#btnExportarHistorial').click(function() {
        var items = $('#ulHistorial li:not(.gd-empty)');
        if (!items.length) return;
        var rows = ['Tipo,Valor'];
        items.each(function() {
            var tipo = $(this).find('.gd-badge').text().trim();
            var valor = $(this).find('.gd-value').text().trim();
            rows.push(tipo + ',' + valor);
        });
        var blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'historial_api_generadordata.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    // ── Limpiar historial ────────────────────────────────────
    $('#btnLimpiarHistorial').click(function() {
        $('#ulHistorial').html('<li class="gd-empty" id="historialVacio">Sin generaciones aún</li>');
    });

});
