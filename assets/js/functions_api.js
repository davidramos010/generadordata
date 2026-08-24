$(document).ready(function() {

    // ── Configuración API base URL (usada solo para mostrar en curl) ────
    var API_BASE_URL = 'http://web/';

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

    // ── Quicknav: alto del navbar + scrollspy de secciones ─────
    var $navbar = $('.gd-navbar');
    var $quickNav = $('#quickNav');
    if ($quickNav.length) {
        var updateNavbarHeight = function() {
            document.documentElement.style.setProperty('--gd-navbar-h', $navbar.outerHeight() + 'px');
        };
        updateNavbarHeight();
        $(window).on('resize', updateNavbarHeight);

        var pills = document.querySelectorAll('.gd-quicknav-pill');
        var sections = document.querySelectorAll('.gd-widget-section');
        if ('IntersectionObserver' in window && sections.length) {
            var navHeight = $navbar.outerHeight() + $quickNav.outerHeight();
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        pills.forEach(function(pill) {
                            pill.classList.toggle('active', pill.dataset.target === entry.target.id);
                        });
                    }
                });
            }, { rootMargin: '-' + navHeight + 'px 0px -70% 0px', threshold: 0 });
            sections.forEach(function(section) { observer.observe(section); });
        }
    }

    // ══════════════════════════════════════════════════════════
    // Utilidades compartidas por las 3 secciones
    // ══════════════════════════════════════════════════════════

    // ── curl con syntax highlighting (GET o POST, con body opcional) ──
    function buildCurlHtml(method, url, bodyObj) {
        var html = '<span class="curl-kw">curl</span> <span class="curl-flag">--request</span> <span class="curl-val">' + method + '</span> \\\n';
        if (method === 'GET') {
            html += '  <span class="curl-flag">--get</span> <span class="curl-url">"' + url + '"</span> \\\n';
        } else {
            html += '  <span class="curl-url">"' + url + '"</span> \\\n';
        }
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Authorization: Bearer {YOUR_AUTH_KEY}"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Content-Type: application/json"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Accept: application/json"</span>';
        if (bodyObj) {
            html += ' \\\n  <span class="curl-flag">--data</span> <span class="curl-val">\'' + JSON.stringify(bodyObj) + '\'</span>';
        }
        return html;
    }

    function buildCurlText(method, url, bodyObj) {
        var text = 'curl --request ' + method + ' \\\n';
        if (method === 'GET') {
            text += '  --get "' + url + '" \\\n';
        } else {
            text += '  "' + url + '" \\\n';
        }
        text += '  --header "Authorization: Bearer {YOUR_AUTH_KEY}" \\\n';
        text += '  --header "Content-Type: application/json" \\\n';
        text += '  --header "Accept: application/json"';
        if (bodyObj) {
            text += ' \\\n  --data \'' + JSON.stringify(bodyObj) + '\'';
        }
        return text;
    }

    // ── Llamada AJAX genérica al proxy PHP ─────────────────────
    function llamarProxy(action, params, onSuccess, btn, btnLabel) {
        if (btn) btn.prop('disabled', true).text('⏳ Procesando...');

        $.ajax({
            url: 'api_proxy.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: action, params: params || {} }),
            dataType: 'json',
            success: function(response) {
                if (btn) btn.prop('disabled', false).text(btnLabel);

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }
                onSuccess(response.data);
            },
            error: function(xhr) {
                if (btn) btn.prop('disabled', false).text(btnLabel);
                var msg = 'Error al conectar con la API';
                try {
                    var err = JSON.parse(xhr.responseText);
                    msg = err.error || err.message || msg;
                } catch (e) {}
                alert(msg);
            }
        });
    }

    // ── Historial (genérico, por sección) ──────────────────────
    function agregarHistorial(ulId, emptyId, badgeText, valueText) {
        $('#' + emptyId).hide();
        var item = $('<li></li>');
        item.append('<span class="gd-badge">' + badgeText + '</span>');
        item.append('<span class="gd-value">' + valueText + '</span>');
        $('#' + ulId).prepend(item);
        $('#' + ulId + ' li:gt(1000)').remove();
    }

    function exportarHistorial(ulId, filename) {
        var items = $('#' + ulId + ' li:not(.gd-empty)');
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
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    }

    function limpiarHistorial(ulId, emptyId, emptyText) {
        $('#' + ulId).html('<li class="gd-empty" id="' + emptyId + '">' + emptyText + '</li>');
    }

    // ── Resultado de validación ─────────────────────────────────
    function mostrarResultadoValidacion(containerId, ok, mensaje) {
        $('#' + containerId).removeClass('valid invalid').addClass(ok ? 'valid' : 'invalid').text(mensaje);
    }

    // ── Copiar texto al portapapeles + alerta ───────────────────
    function copiarTexto(texto) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(texto);
        } else {
            var t = $('<textarea>').val(texto).appendTo('body');
            t[0].select();
            document.execCommand('copy');
            t.remove();
        }
        var a = $('#alertCopiItem');
        a.fadeIn(200).delay(1800).fadeOut(400);
    }

    // ══════════════════════════════════════════════════════════
    // Sección 1: Generación de Documentos
    // ══════════════════════════════════════════════════════════

    var tipoActivoDocs = 'DNI';
    var endpointMapDocs = { DNI: 'generate-dni', NIF: 'generate-nif', NIE: 'generate-nie', SSN: 'generate-ssn' };

    function actionForDocs(tipo, tipoCif) {
        if (tipo === 'CIF') return tipoCif ? 'generate-cif-by-type' : 'generate-cif';
        return endpointMapDocs[tipo];
    }

    function buildApiUrlDocs(tipo, cantidad, tipoCif) {
        var url = API_BASE_URL + '/' + actionForDocs(tipo, tipoCif) + '?result=' + cantidad;
        if (tipo === 'CIF' && tipoCif) url += '&type=' + tipoCif;
        return url;
    }

    function actualizarCurlDocs() {
        var cantidad = $('#selectCantidadDocs').val();
        var tipoCif = $('#selectTipoCif').val();
        $('#curlDisplayDocs').html(buildCurlHtml('GET', buildApiUrlDocs(tipoActivoDocs, cantidad, tipoCif)));
    }

    function toggleCifSelector() {
        $('#cifTypeRow').toggleClass('visible', tipoActivoDocs === 'CIF');
    }

    actualizarCurlDocs();
    toggleCifSelector();

    $('#cardDocs .gd-tab').click(function() {
        tipoActivoDocs = $(this).data('tipo');
        $(this).closest('.gd-tabs').find('.gd-tab').removeClass('active');
        $(this).addClass('active');
        toggleCifSelector();
        actualizarCurlDocs();
    });
    $('#selectCantidadDocs, #selectTipoCif').change(actualizarCurlDocs);

    $('#btnGenerarDocs').click(function() {
        var cantidad = parseInt($('#selectCantidadDocs').val());
        var tipoCif = $('#selectTipoCif').val();
        var action = actionForDocs(tipoActivoDocs, tipoCif);
        var params = { result: cantidad };
        if (tipoActivoDocs === 'CIF' && tipoCif) params.type = tipoCif;

        llamarProxy(action, params, function(datos) {
            if (!Array.isArray(datos)) datos = [datos];
            for (var i = 0; i < datos.length; i++) {
                agregarHistorial('ulHistorialDocs', 'historialVacioDocs', tipoActivoDocs, datos[i]);
            }
        }, $(this), '✨ Generar');
    });

    $('#btnCopiarCurlDocs').click(function() {
        var cantidad = $('#selectCantidadDocs').val();
        var tipoCif = $('#selectTipoCif').val();
        copiarTexto(buildCurlText('GET', buildApiUrlDocs(tipoActivoDocs, cantidad, tipoCif)));
    });

    $('#btnValidarDocs').click(function() {
        var documento = $('#inputValidarDocs').val().trim();
        var tipo = $('#selectTipoValidarDocs').val();
        if (!documento) { alert('Introduce un documento a validar.'); return; }

        var params = { document: documento };
        if (tipo) params.type = tipo;

        llamarProxy('validate-document', params, function(data) {
            var ok = data.message === 'VALIDO';
            var etiquetaTipo = data.type ? '[' + data.type + '] ' : '';
            mostrarResultadoValidacion('validationResultDocs', ok, data.document + ' → ' + etiquetaTipo + data.message);
            agregarHistorial('ulHistorialDocs', 'historialVacioDocs', 'VALIDAR', documento + ' → ' + data.message);
        }, $(this), 'Validar');
    });

    $('#btnExportarHistorialDocs').click(function() { exportarHistorial('ulHistorialDocs', 'historial_documentos.csv'); });
    $('#btnLimpiarHistorialDocs').click(function() { limpiarHistorial('ulHistorialDocs', 'historialVacioDocs', 'Sin generaciones aún'); });

    // ══════════════════════════════════════════════════════════
    // Sección 2: Datos Financieros
    // ══════════════════════════════════════════════════════════

    var tipoActivoFin = 'IBAN';
    var endpointMapFin = { IBAN: 'generate-iban', CCC: 'generate-cuenta', TARJETA: 'generate-tarjeta' };

    function buildApiUrlFin(tipo, tipoTarjeta) {
        var url = API_BASE_URL + '/' + endpointMapFin[tipo];
        if (tipo === 'TARJETA' && tipoTarjeta) url += '?type=' + tipoTarjeta;
        return url;
    }

    function actualizarCurlFinanzas() {
        var tipoTarjeta = $('#selectTipoTarjeta').val();
        $('#curlDisplayFinanzas').html(buildCurlHtml('GET', buildApiUrlFin(tipoActivoFin, tipoTarjeta)));
    }

    function toggleTarjetaSelector() {
        $('#tarjetaTypeRow').toggleClass('visible', tipoActivoFin === 'TARJETA');
    }

    actualizarCurlFinanzas();
    toggleTarjetaSelector();

    $('#cardFinanzas .gd-tab').click(function() {
        tipoActivoFin = $(this).data('tipo');
        $(this).closest('.gd-tabs').find('.gd-tab').removeClass('active');
        $(this).addClass('active');
        toggleTarjetaSelector();
        actualizarCurlFinanzas();
    });
    $('#selectTipoTarjeta').change(actualizarCurlFinanzas);

    $('#btnGenerarFinanzas').click(function() {
        var action = endpointMapFin[tipoActivoFin];
        var params = {};
        if (tipoActivoFin === 'TARJETA') {
            var tipoTarjeta = $('#selectTipoTarjeta').val();
            if (tipoTarjeta) params.type = tipoTarjeta;
        }

        llamarProxy(action, params, function(data) {
            var valorPrincipal, sub = '';
            if (tipoActivoFin === 'IBAN') {
                valorPrincipal = data.formatted || data.iban;
            } else if (tipoActivoFin === 'CCC') {
                valorPrincipal = data.formatted || data.ccc;
            } else {
                valorPrincipal = data.formatted || data.card_number;
                sub = data.type + ' · exp ' + data.expiry + ' · cvv ' + data.cvv;
            }

            var html = '<span>' + valorPrincipal;
            if (sub) html += '<span class="gd-display-sub">' + sub + '</span>';
            html += '</span>';
            $('#displayFinanzas').addClass('has-value').html(html);

            agregarHistorial('ulHistorialFinanzas', 'historialVacioFinanzas', tipoActivoFin, valorPrincipal);
        }, $(this), '✨ Generar');
    });

    $('#btnCopiarCurlFinanzas').click(function() {
        var tipoTarjeta = $('#selectTipoTarjeta').val();
        copiarTexto(buildCurlText('GET', buildApiUrlFin(tipoActivoFin, tipoTarjeta)));
    });

    $('#btnValidarIban').click(function() {
        var iban = $('#inputValidarIban').val().trim();
        if (!iban) { alert('Introduce un IBAN a validar.'); return; }

        llamarProxy('validate-iban', { iban: iban }, function(data) {
            mostrarResultadoValidacion('validationResultFinanzas', !!data.valid, (data.formatted || data.iban) + ' → ' + data.message);
            agregarHistorial('ulHistorialFinanzas', 'historialVacioFinanzas', 'VALIDAR', iban + ' → ' + data.message);
        }, $(this), 'Validar');
    });

    $('#btnExportarHistorialFinanzas').click(function() { exportarHistorial('ulHistorialFinanzas', 'historial_financiero.csv'); });
    $('#btnLimpiarHistorialFinanzas').click(function() { limpiarHistorial('ulHistorialFinanzas', 'historialVacioFinanzas', 'Sin generaciones aún'); });

    // ══════════════════════════════════════════════════════════
    // Sección 3: CUPS
    // ══════════════════════════════════════════════════════════

    var tipoActivoCups = 'electricidad';

    function buildBodyCups() {
        return {
            tipo: tipoActivoCups,
            distribuidora: $('#inputDistribuidoraCups').val().trim(),
            cantidad: parseInt($('#selectCantidadCups').val()),
            incluirSufijo: $('#chkIncluirSufijoCups').is(':checked')
        };
    }

    function actualizarCurlCups() {
        var url = API_BASE_URL + '/cups/generate';
        $('#curlDisplayCups').html(buildCurlHtml('POST', url, buildBodyCups()));
    }

    actualizarCurlCups();

    $('#cardCups .gd-tab').click(function() {
        tipoActivoCups = $(this).data('tipo');
        $(this).closest('.gd-tabs').find('.gd-tab').removeClass('active');
        $(this).addClass('active');
        actualizarCurlCups();
    });
    $('#inputDistribuidoraCups, #selectCantidadCups, #chkIncluirSufijoCups').on('input change', actualizarCurlCups);

    $('#btnGenerarCups').click(function() {
        var body = buildBodyCups();
        if (!/^[0-9]{4}$/.test(body.distribuidora)) {
            alert('La distribuidora debe ser un código de exactamente 4 dígitos.');
            return;
        }

        llamarProxy('cups-generate', body, function(data) {
            var lista = data.cups || [];
            if (!Array.isArray(lista)) lista = [lista];
            var badge = (data.tipo || tipoActivoCups).toUpperCase();
            for (var i = 0; i < lista.length; i++) {
                agregarHistorial('ulHistorialCups', 'historialVacioCups', badge, lista[i]);
            }
        }, $(this), '✨ Generar');
    });

    $('#btnCopiarCurlCups').click(function() {
        copiarTexto(buildCurlText('POST', API_BASE_URL + '/cups/generate', buildBodyCups()));
    });

    $('#btnValidarCups').click(function() {
        var cups = $('#inputValidarCups').val().trim();
        if (!cups) { alert('Introduce un código CUPS a validar.'); return; }

        llamarProxy('cups-validate', { cups: cups }, function(data) {
            if (data.valido) {
                var d = data.detalles || {};
                var msg = cups + ' → VALIDO\nDistribuidora: ' + d.distribuidora + ' · Suministro: ' + d.suministro + ' · Control: ' + d.controlRecibido;
                mostrarResultadoValidacion('validationResultCups', true, msg);
            } else {
                var errores = (data.errores || []).join(', ');
                mostrarResultadoValidacion('validationResultCups', false, cups + ' → INVALIDO: ' + errores);
            }
            agregarHistorial('ulHistorialCups', 'historialVacioCups', 'VALIDAR', cups + ' → ' + (data.valido ? 'VALIDO' : 'INVALIDO'));
        }, $(this), 'Validar');
    });

    $('#btnExportarHistorialCups').click(function() { exportarHistorial('ulHistorialCups', 'historial_cups.csv'); });
    $('#btnLimpiarHistorialCups').click(function() { limpiarHistorial('ulHistorialCups', 'historialVacioCups', 'Sin generaciones aún'); });

});
