(function () {
    'use strict';

    var ENDPOINT = '/base64controller.php';
    var MAX_PDF_BYTES = 10 * 1024 * 1024;
    var MAX_B64_CHARS = 14 * 1024 * 1024;
    var TIMEOUT_MS = 60000;

    var $ = function (id) { return document.getElementById(id); };

    // ── Utilidades ─────────────────────────────────────────────
    function formatBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1).replace('.', ',') + ' KB';
        return (n / (1024 * 1024)).toFixed(2).replace('.', ',') + ' MB';
    }

    function showAlert(el, type, text) {
        el.className = 'gd-alert gd-alert-' + type;
        el.textContent = text;
        el.hidden = false;
    }

    function hide(el) { el.hidden = true; }

    function setBusy(btn, busy) {
        if (busy) {
            btn.dataset.label = btn.textContent;
            btn.textContent = '⏳ Procesando…';
            btn.disabled = true;
        } else {
            btn.textContent = btn.dataset.label || btn.textContent;
            btn.disabled = false;
        }
    }

    function renderInfo(list, rows) {
        list.textContent = '';
        rows.forEach(function (row) {
            var li = document.createElement('li');
            li.textContent = row[0];
            var span = document.createElement('span');
            span.textContent = row[1];
            li.appendChild(span);
            list.appendChild(li);
        });
        list.hidden = false;
    }

    function infoRows(info) {
        return [
            ['Tamaño', formatBytes(info.size)],
            ['Versión', 'PDF ' + info.version],
            ['Páginas', info.pages > 0 ? String(info.pages) : 'No detectadas'],
            ['Cifrado', info.encrypted ? 'Sí' : 'No']
        ];
    }

    function post(body) {
        var ctrl = new AbortController();
        var timer = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);
        return fetch(ENDPOINT, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal
        }).then(function (res) {
            return res.json().catch(function () {
                return { ok: false, message: res.status === 413
                    ? 'El archivo supera el tamaño máximo que acepta el servidor.'
                    : 'Respuesta inesperada del servidor (HTTP ' + res.status + ').' };
            });
        }).catch(function (err) {
            return { ok: false, message: err.name === 'AbortError'
                ? 'La conversión ha tardado demasiado. Inténtalo de nuevo.'
                : 'No se ha podido conectar con el servidor.' };
        }).finally(function () { clearTimeout(timer); });
    }

    function base64ToBlob(b64) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new Blob([bytes], { type: 'application/pdf' });
    }

    function copyText(text, btn) {
        var done = function () {
            var label = btn.textContent;
            btn.textContent = '✓ Copiado';
            setTimeout(function () { btn.textContent = label; }, 1600);
        };
        var fallback = function () {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            if (ok) done();
            else {
                encOutput.focus();
                encOutput.select();
            }
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else {
            fallback();
        }
    }

    // ── Pestañas ───────────────────────────────────────────────
    document.querySelectorAll('#cardBase64 .gd-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('#cardBase64 .gd-tab').forEach(function (t) {
                t.classList.toggle('active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });
            document.querySelectorAll('#cardBase64 .gd-panel').forEach(function (p) {
                p.hidden = p.id !== tab.dataset.panel;
            });
        });
    });

    // ── Base64 → PDF ───────────────────────────────────────────
    var decInput = $('b64Input');
    var decCount = $('b64Count');
    var decBtn = $('btnDecode');
    var decClear = $('btnDecodeClear');
    var decAlert = $('decodeAlert');
    var decInfo = $('decodeInfo');
    var decResult = $('decodeResult');
    var decPreview = $('decodePreview');
    var decDownload = $('btnDownloadPdf');
    var pdfUrl = null;

    function resetDecodeResult() {
        hide(decAlert);
        hide(decInfo);
        hide(decResult);
        decPreview.hidden = true;
        decPreview.removeAttribute('src');
        if (pdfUrl) { URL.revokeObjectURL(pdfUrl); pdfUrl = null; }
    }

    decInput.addEventListener('input', function () {
        decCount.textContent = decInput.value.length.toLocaleString('es-ES') + ' caracteres';
    });

    decClear.addEventListener('click', function () {
        decInput.value = '';
        decCount.textContent = '0 caracteres';
        resetDecodeResult();
        decInput.focus();
    });

    decBtn.addEventListener('click', function () {
        resetDecodeResult();
        var text = decInput.value.trim();
        if (!text) { showAlert(decAlert, 'error', 'Pega un texto en Base64 para convertirlo.'); return; }
        if (text.length > MAX_B64_CHARS) {
            showAlert(decAlert, 'error', 'El texto es demasiado largo: el PDF resultante superaría el límite de 10 MB.');
            return;
        }

        var body = new FormData();
        body.append('action', 'decode');
        body.append('base64', text);

        setBusy(decBtn, true);
        post(body).then(function (data) {
            if (!data.ok) {
                showAlert(decAlert, 'error', data.message || 'No se ha podido crear el PDF.');
                return;
            }
            renderInfo(decInfo, infoRows(data.info));
            pdfUrl = URL.createObjectURL(base64ToBlob(data.base64));
            decDownload.href = pdfUrl;

            if (data.info.active) {
                showAlert(decAlert, 'warn', 'Este PDF contiene elementos activos (JavaScript, archivos adjuntos o acciones automáticas). Por seguridad no se muestra la vista previa: descárgalo y ábrelo solo si confías en su origen.');
            } else {
                showAlert(decAlert, 'ok', 'PDF válido. Puedes verlo abajo o descargarlo.');
                decPreview.src = pdfUrl;
                decPreview.hidden = false;
            }
            decResult.hidden = false;
        }).finally(function () { setBusy(decBtn, false); });
    });

    // ── PDF → Base64 ───────────────────────────────────────────
    var encDrop = $('pdfDrop');
    var encFile = $('pdfFile');
    var encName = $('pdfFileName');
    var encBtn = $('btnEncode');
    var encAlert = $('encodeAlert');
    var encInfo = $('encodeInfo');
    var encResult = $('encodeResult');
    var encFormat = $('b64Format');
    var encOutput = $('b64Output');
    var encOutCount = $('b64OutputCount');
    var encCopy = $('btnCopyB64');
    var encSave = $('btnSaveB64');
    var selected = null;
    var encoded = null;

    function resetEncodeResult() {
        hide(encAlert);
        hide(encInfo);
        hide(encResult);
        encOutput.value = '';
        encoded = null;
    }

    function selectFile(file) {
        resetEncodeResult();
        selected = null;
        encBtn.disabled = true;
        encName.textContent = 'Ningún archivo seleccionado';
        if (!file) return;

        encName.textContent = file.name + ' · ' + formatBytes(file.size);
        if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) {
            showAlert(encAlert, 'error', 'Solo se admiten archivos PDF.');
            return;
        }
        if (file.size === 0) { showAlert(encAlert, 'error', 'El archivo está vacío.'); return; }
        if (file.size > MAX_PDF_BYTES) {
            showAlert(encAlert, 'error', 'El PDF pesa ' + formatBytes(file.size) + ' y el máximo permitido es 10 MB.');
            return;
        }
        selected = file;
        encBtn.disabled = false;
    }

    function formatOutput() {
        if (!encoded) return;
        var b64 = encoded.base64;
        var uri = 'data:application/pdf;base64,' + b64;
        var outputs = {
            plain: b64,
            datauri: uri,
            json: JSON.stringify({ filename: encoded.filename, mime: 'application/pdf', data: b64 }),
            embed: '<embed src="' + uri + '" type="application/pdf" width="100%" height="600">'
        };
        encOutput.value = outputs[encFormat.value] || b64;
        encOutCount.textContent = encOutput.value.length.toLocaleString('es-ES') + ' caracteres';
    }

    encFile.addEventListener('change', function () { selectFile(encFile.files[0]); });

    ['dragenter', 'dragover'].forEach(function (ev) {
        encDrop.addEventListener(ev, function (e) { e.preventDefault(); encDrop.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        encDrop.addEventListener(ev, function (e) { e.preventDefault(); encDrop.classList.remove('is-dragover'); });
    });
    encDrop.addEventListener('drop', function (e) {
        var file = e.dataTransfer && e.dataTransfer.files[0];
        if (file) selectFile(file);
    });

    encBtn.addEventListener('click', function () {
        if (!selected) return;
        resetEncodeResult();

        var body = new FormData();
        body.append('action', 'encode');
        body.append('pdf', selected, selected.name);

        setBusy(encBtn, true);
        post(body).then(function (data) {
            if (!data.ok) {
                showAlert(encAlert, 'error', data.message || 'No se ha podido convertir el PDF.');
                return;
            }
            encoded = data;
            var rows = infoRows(data.info);
            rows.push(['Longitud Base64', data.base64.length.toLocaleString('es-ES')]);
            renderInfo(encInfo, rows);
            if (data.info.active) {
                showAlert(encAlert, 'warn', 'Aviso: este PDF contiene elementos activos (JavaScript, adjuntos o acciones automáticas).');
            }
            formatOutput();
            encResult.hidden = false;
        }).finally(function () {
            setBusy(encBtn, false);
            encBtn.disabled = !selected;
        });
    });

    encFormat.addEventListener('change', formatOutput);
    encCopy.addEventListener('click', function () { if (encOutput.value) copyText(encOutput.value, encCopy); });
    encSave.addEventListener('click', function () {
        if (!encOutput.value) return;
        var url = URL.createObjectURL(new Blob([encOutput.value], { type: 'text/plain' }));
        var a = document.createElement('a');
        a.href = url;
        a.download = (encoded ? encoded.filename.replace(/\.pdf$/i, '') : 'documento') + '.base64.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
})();
