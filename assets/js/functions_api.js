$(document).ready(function() {

    // Configuracion API base URL (usada solo para mostrar en curl).
    var API_BASE_URL = 'http://localhost:8001';

    // Tema light / dark.
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

    var seccionActiva = 'documentos';
    var tipoDocumentoActivo = 'DNI';
    var tipoBancoActivo = 'IBAN';

    var documentoEndpoints = {
        'DNI': { method: 'GET', endpoint: 'api/generate-dni' },
        'NIF': { method: 'GET', endpoint: 'api/generate-nif' },
        'NIE': { method: 'GET', endpoint: 'api/generate-nie' },
        'CIF': { method: 'GET', endpoint: 'api/generate-cif-by-type' }
    };

    var cupsEndpoint = { method: 'POST', endpoint: 'api/cups/generate' };

    var bancoEndpoints = {
        'IBAN': { method: 'GET', endpoint: 'api/generate-iban' },
        'VALIDAR_IBAN': { method: 'GET', endpoint: 'api/validate-iban' },
        'TC': { method: 'GET', endpoint: 'api/generate-tarjeta' },
        'VALIDAR_TC': { method: 'POST', endpoint: 'api_proxy.php?action=validate-tc' }
    };

    function trimSlashes(value) {
        return String(value || '').replace(/^\/+|\/+$/g, '');
    }

    function buildUrl(endpoint, params) {
        if (String(endpoint).indexOf('api_proxy.php') === 0) {
            return endpoint;
        }
        var base = API_BASE_URL.replace(/\/+$/g, '');
        var url = base + '/' + trimSlashes(endpoint);
        var query = $.param(params || {});
        return query ? url + '?' + query : url;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildJsonBlock(data) {
        return JSON.stringify(data, null, 2).replace(/\n/g, '\n  ');
    }

    function buildCurlHtml(method, url, data) {
        var html = '<span class="curl-kw">curl</span> <span class="curl-flag">--request</span> <span class="curl-val">' + method + '</span> \\\n';
        html += '  <span class="curl-url">"' + escapeHtml(url) + '"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Authorization: Bearer {YOUR_AUTH_KEY}"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Content-Type: application/json"</span> \\\n';
        html += '  <span class="curl-flag">--header</span> <span class="curl-val">"Accept: application/json"</span>';

        if (data) {
            html += ' \\\n  <span class="curl-flag">--data</span> <span class="curl-val">\'' + escapeHtml(buildJsonBlock(data)) + '\'</span>';
        }

        return html;
    }

    function buildCurlText(method, url, data) {
        var text = 'curl --request ' + method + ' \\\n';
        text += '  "' + url + '" \\\n';
        text += '  --header "Authorization: Bearer {YOUR_AUTH_KEY}" \\\n';
        text += '  --header "Content-Type: application/json" \\\n';
        text += '  --header "Accept: application/json"';

        if (data) {
            text += ' \\\n  --data \'' + buildJsonBlock(data) + '\'';
        }

        return text;
    }

    function getDocumentoRequest() {
        var cantidad = parseInt($('#selectCantidad').val(), 10);
        var tipoCif = $('#selectTipoCif').val();
        var config = documentoEndpoints[tipoDocumentoActivo] || documentoEndpoints.DNI;
        var params = { result: cantidad };

        if (tipoDocumentoActivo === 'CIF') {
            params.type = tipoCif || 'B';
        }

        return {
            categoria: 'documentos',
            tipo: tipoDocumentoActivo,
            cantidad: cantidad,
            tipoCif: tipoDocumentoActivo === 'CIF' ? tipoCif : null,
            label: tipoDocumentoActivo,
            method: config.method,
            url: buildUrl(config.endpoint, params),
            body: null
        };
    }

    function getCupsRequest() {
        var body = {
            tipo: $('#selectCupsTipo').val(),
            distribuidora: $('#inputCupsDistribuidora').val().trim(),
            cantidad: parseInt($('#selectCupsCantidad').val(), 10),
            incluirSufijo: $('#selectCupsSufijo').val() === 'true'
        };

        return {
            categoria: 'cups',
            tipo: 'CUPS',
            label: 'CUPS',
            method: cupsEndpoint.method,
            url: buildUrl(cupsEndpoint.endpoint),
            body: body,
            data: body
        };
    }

    function getBancoRequest() {
        var config = bancoEndpoints[tipoBancoActivo] || bancoEndpoints.IBAN;
        var data = {};

        if (tipoBancoActivo === 'VALIDAR_IBAN') {
            data.iban = $('#inputIbanValidar').val().trim();
        }

        if (tipoBancoActivo === 'TC') {
            data.type = $('#selectTcTipoGenerar').val();
        }

        if (tipoBancoActivo === 'VALIDAR_TC') {
            data.tipo = $('#selectTcTipo').val();
            data.numero = $('#inputTcValidar').val().trim();
        }

        return {
            categoria: 'bancos',
            tipo: tipoBancoActivo,
            label: getBancoLabel(tipoBancoActivo),
            method: config.method,
            url: buildUrl(config.endpoint, config.method === 'GET' ? data : null),
            body: config.method === 'GET' ? null : data,
            data: data
        };
    }

    function getBancoLabel(tipo) {
        var map = {
            'IBAN': 'IBAN',
            'VALIDAR_IBAN': 'VALIDAR IBAN',
            'TC': 'TC',
            'VALIDAR_TC': 'VALIDAR TC'
        };
        return map[tipo] || 'BANCO';
    }

    function getActiveRequest() {
        if (seccionActiva === 'cups') {
            return getCupsRequest();
        }
        if (seccionActiva === 'bancos') {
            return getBancoRequest();
        }
        return getDocumentoRequest();
    }

    function actualizarCurl() {
        var request = getActiveRequest();
        $('#curlDisplay').html(buildCurlHtml(request.method, request.url, request.body));
    }

    function toggleCifSelector() {
        $('#cifTypeRow').toggleClass('visible', tipoDocumentoActivo === 'CIF');
    }

    function updateHeader() {
        var titles = {
            documentos: {
                title: 'Generador API DNI / NIF / NIE / CIF',
                subtitle: 'Genera números válidos vía API REST'
            },
            cups: {
                title: 'Generador API CUPS',
                subtitle: 'Genera codigos CUPS via API REST'
            },
            bancos: {
                title: 'Generador API Bancos',
                subtitle: 'Genera y valida datos financieros via API REST'
            }
        };
        var data = titles[seccionActiva] || titles.documentos;
        $('.gd-card-header h2').text(data.title);
        $('.gd-card-header p').text(data.subtitle);
    }

    function updateSectionMode() {
        $('#generadores')
            .removeClass('documentos-mode cups-mode bancos-mode')
            .addClass(seccionActiva + '-mode');
        $('.gd-api-panel').removeClass('active');
        $('#' + seccionActiva + 'Panel').addClass('active');
        $('.gd-main-tabs .gd-tab').removeClass('active');
        $('.gd-main-tabs .gd-tab[data-section="' + seccionActiva + '"]').addClass('active');
        updateHeader();
        updateBankMode();
        setLoading(false);
        actualizarCurl();
    }

    function updateBankMode() {
        $('.gd-bank-mode').removeClass('active');

        if (tipoBancoActivo === 'VALIDAR_IBAN') {
            $('#bankValidateIbanPanel').addClass('active');
        } else if (tipoBancoActivo === 'VALIDAR_TC') {
            $('#bankValidateTcPanel').addClass('active');
        } else {
            $('#bankGeneratePanel').addClass('active');
        }

        $('#tcGenerateTypeRow').toggleClass('visible', tipoBancoActivo === 'TC');
        $('.gd-bank-tabs .gd-tab').removeClass('active');
        $('.gd-bank-tabs .gd-tab[data-banco="' + tipoBancoActivo + '"]').addClass('active');
    }

    function getActionText() {
        if (seccionActiva === 'bancos' && (tipoBancoActivo === 'VALIDAR_IBAN' || tipoBancoActivo === 'VALIDAR_TC')) {
            return 'Validar';
        }
        return 'Generar';
    }

    function setLoading(isLoading) {
        var $btn = $('#btnGenerar');
        var text = getActionText();
        $btn.prop('disabled', isLoading).text(isLoading ? '⏳ Procesando...' : '✨ ' + text);
    }

    function normalizeItems(response) {
        var datos = response.data || response;
        if (datos && datos.data) {
            datos = datos.data;
        }
        return Array.isArray(datos) ? datos : [datos];
    }

    function stringifyValue(value) {
        if (value == null) {
            return '';
        }
        if (typeof value === 'object') {
            return JSON.stringify(value);
        }
        return String(value);
    }

    function addHistorial(label, datos) {
        $('#historialVacio').hide();
        for (var i = 0; i < datos.length; i++) {
            var item = $('<li></li>');
            item.append('<span class="gd-badge">' + escapeHtml(label) + '</span>');
            item.append('<span class="gd-value">' + escapeHtml(stringifyValue(datos[i])) + '</span>');
            $('#ulHistorial').prepend(item);
        }
        $('#ulHistorial li:gt(1000)').remove();
    }

    toggleCifSelector();
    updateSectionMode();

    $('.gd-main-tabs .gd-tab').click(function() {
        seccionActiva = $(this).data('section');
        updateSectionMode();
    });

    $('[data-documento]').click(function() {
        tipoDocumentoActivo = $(this).data('documento');
        $('[data-documento]').removeClass('active');
        $(this).addClass('active');
        toggleCifSelector();
        actualizarCurl();
    });

    $('[data-banco]').click(function() {
        tipoBancoActivo = $(this).data('banco');
        updateBankMode();
        setLoading(false);
        actualizarCurl();
    });

    $('#selectCantidad, #selectTipoCif, #selectCupsTipo, #selectCupsCantidad, #selectCupsSufijo, #selectTcTipo, #selectTcTipoGenerar').change(actualizarCurl);
    $('#inputCupsDistribuidora, #inputIbanValidar, #inputTcValidar').on('input', actualizarCurl);

    $('#btnGenerar').click(function() {
        var request = getActiveRequest();

        setLoading(true);

        $.ajax({
            url: 'api_proxy.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                categoria: request.categoria,
                tipo: request.tipo,
                cantidad: request.cantidad || (request.body ? request.body.cantidad : null),
                tipoCif: request.tipoCif || null,
                data: request.data || request.body || {}
            }),
            dataType: 'json',
            success: function(response) {
                setLoading(false);

                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }

                addHistorial(request.label, normalizeItems(response));
            },
            error: function(xhr) {
                setLoading(false);
                var msg = 'Error al conectar con la API';
                try {
                    var err = JSON.parse(xhr.responseText);
                    msg = err.error || err.message || msg;
                } catch(e) {}
                alert(msg);
            }
        });
    });

    $('#btnCopiarCurl').click(function() {
        var request = getActiveRequest();
        var curlText = buildCurlText(request.method, request.url, request.body);

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

    $('#btnExportarHistorial').click(function() {
        var items = $('#ulHistorial li:not(.gd-empty)');
        if (!items.length) return;
        var rows = ['Tipo,Valor'];
        items.each(function() {
            var tipo = $(this).find('.gd-badge').text().trim();
            var valor = $(this).find('.gd-value').text().trim();
            rows.push('"' + tipo.replace(/"/g, '""') + '","' + valor.replace(/"/g, '""') + '"');
        });
        var blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'historial_api_generadordata.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    $('#btnLimpiarHistorial').click(function() {
        $('#ulHistorial').html('<li class="gd-empty" id="historialVacio">Sin generaciones aún</li>');
    });

});
