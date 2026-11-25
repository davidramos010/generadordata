(function () {
    'use strict';

    var ENDPOINT = '/jwtcontroller.php';
    var TIMEOUT_MS = 20000;
    var HMAC = /^HS(256|384|512)$/;

    // Claves y tokens de ejemplo: generados solo para esta página, no se usan en ningún sistema real.
    var EJEMPLOS = {
        "HS256": {
            "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1c3VhcmlvLTQyIiwibmFtZSI6IkFuYSBQXHUwMGU5cmV6IiwiZW1haWwiOiJhbmFAZWplbXBsby5jb20iLCJyb2xlIjoiYWRtaW4iLCJpc3MiOiJodHRwczovL2F1dGguZWplbXBsby5jb20iLCJhdWQiOiJhcGkuZWplbXBsby5jb20iLCJpYXQiOjE3NjcyMjU2MDAsImV4cCI6NDEwMjQ0NDgwMH0.z06F0f30UaL1z6U15BaKd4ePmSfyxCQruBVp3QLYQXo",
            "keyType": "secret",
            "key": "clave-de-ejemplo-solo-para-pruebas-32b"
        },
        "RS256": {
            "token": "eyJhbGciOiJSUzI1NiIsImtpZCI6ImVqZW1wbG8tcnNhIiwidHlwIjoiSldUIn0.eyJzdWIiOiJ1c3VhcmlvLTQyIiwibmFtZSI6IkFuYSBQXHUwMGU5cmV6IiwiZW1haWwiOiJhbmFAZWplbXBsby5jb20iLCJyb2xlIjoiYWRtaW4iLCJpc3MiOiJodHRwczovL2F1dGguZWplbXBsby5jb20iLCJhdWQiOiJhcGkuZWplbXBsby5jb20iLCJpYXQiOjE3NjcyMjU2MDAsImV4cCI6NDEwMjQ0NDgwMH0.m617CuE-AhWxV-8nBaKt2tvLHa7GJtbbFe6tVKlDRQlhrCfNgJLp-rFQoBZh2F3UxZGZ-84kkQKKk6-NM4eW6JxK-b2k9TgEVb6GxHEgLz9G6p2WCvdbXIOwWTrgt4mV2eSipOxpEyUKFbjjtHsBTKccC7TLK_Q6s4TWTrAcarO8JyK1s50SujLFfythqLmOFFxpEYCWiB59r5AmVBIEVxjWP7a_N7HgdCe-6kFBaHNxk6QzCxHl9Cqe8yFR58QTQ3tB9YcG5ds51YT6iKhSL6DGNsaIFiqdfKWx79GieQSMSxnuxGsjQ6PP5nChLndYjp7GxJw9KM_Yuq5isg3gNw",
            "keyType": "public_key",
            "key": "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2+BNtxYH/9gahFWFeNPf\nX4cb1v6EGNARwL4NyTzx7ixhd1kdlTMgJIO2YqwUTt54CTh4yOZGXplKQGQdmb6f\nMTvG+tK2OOVXE7h0c4bb7z6GS7fljx48L9yko4oYkpiNDr7wD2RAUABQyaqEuU6x\nSwrnIcbfXTuO8VKdXAmpSxyb1sF9pbsEVXEYUh/X3n1qPswAxJp8zImoADeib4gM\nRdxDVb62ledksaJ7XK4g2/9FK04b7B5v9kCNHvesXzxfC/snhIxFHjSG5PtwrBID\nipjN70DCI6zmMhfYD8/0ameS4WxeNHJ/3L+TJuFYWEoLq6rf3ur1RQu9VTusQBus\nOwIDAQAB\n-----END PUBLIC KEY-----",
            "privateKey": "-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDb4E23Fgf/2BqE\nVYV4099fhxvW/oQY0BHAvg3JPPHuLGF3WR2VMyAkg7ZirBRO3ngJOHjI5kZemUpA\nZB2Zvp8xO8b60rY45VcTuHRzhtvvPoZLt+WPHjwv3KSjihiSmI0OvvAPZEBQAFDJ\nqoS5TrFLCuchxt9dO47xUp1cCalLHJvWwX2luwRVcRhSH9fefWo+zADEmnzMiagA\nN6JviAxF3ENVvraV52SxontcriDb/0UrThvsHm/2QI0e96xfPF8L+yeEjEUeNIbk\n+3CsEgOKmM3vQMIjrOYyF9gPz/RqZ5LhbF40cn/cv5Mm4VhYSgurqt/e6vVFC71V\nO6xAG6w7AgMBAAECggEAJHNtBCXXhvyGeNsiA26XILkUo8cR6Hh7WPP+SRbyF0VY\nQHbk8UUb1lyCMfLLyGoX2uoia/vGPWHgQ13VjRVi7Ws23izByhuLHK5+77VdugMo\nNF6fjzhjS0lNf1HM0mYOjHFETujpwy7U4HnaWEt0F41j1TT+S/CBuVQJg6aTtZW7\n0a5rrKRNaoADs+Z+ZTLC01dBJyRmk0CL5iW/TnPPRIGOYDL7L+ryiB07TSvzP/6P\nXMdcyKlbxehzY7cY78Pvg0wstY5OJZB8+cSQwKGdFpFSzVCg1CKWLlMdREecTSBT\nybGDnqIxZ/GYaabzmc4Mn7f/dh1aANaEdM4HPCeOsQKBgQD45JUqlT9kt30BF8u9\n0w8AHlgIlyjg9R7dnnrmgUQAhOQkcDWffe6AwAysPoAMzh8jsW8qnHh/hyzPpoav\nXwLqiamfbiHAd0Lf/Asn13kykYq+iKhe/mwj2j1fwEtCw/RFALvfeP3U7wkcn1gU\n/U5nXCwUucwLO7klFil7c+OQZwKBgQDiJ5uGF3lEEK94u2JbpnY4mIKxOp4yJdKk\npkgIkT7mcFmWGQr3U2TcBHDcZc/xj1E7FKT9J5qmevKIi3/5djylLROHw5fYDRi0\nwjpqeykKtnUPMnz5BGedQ/uv6r5wqa/uz1UWmQL4IddRDRqld9Dvr1ldLvdrpF48\nTifJ60WRDQKBgQDCPFkw30KWzh9u3+wvmuvE8ulO4vCTBphs6cYN52lzYkJNMYiX\njIa50abSMQBrfdyNVWZlAbGEDLDq6JGJgQNcl8xZUHBkrG3zMNZf5GeAEFcPtMR5\nReKM5D8rX30cgKI7y4PStbCaIWWiAbSSxNAQF72K+yiCx7wIdlAvONSqFwKBgQCg\n/IjdP0B3j1kZnGo7y5dEWmxONOf/0qTLyAJm9t3LtBZRnqoLvTWC2TZt7ZvE3Lx6\nTgvUmaig8cxMHuGOdvhcrGXGsxkYFtqLceebfOiOU6uokcwch2IqtQYpB/VgxaoT\nWUu8tldCwvQ/IhDh0RTOSghXKOO/oFXkn837EpuG0QKBgQCPSJXGW7LQcKJIN9kl\n5gJGDNoOn5Wg9Hu0jaRxil92CXcCqItEVdP6SDtqF2my3ofFVpMdsHKoEOm9JAAf\nCf/5isQMcgEPlux7MqgWmhaxDzQbThvGrbOfedcIUUZlDkt687o7xD+kCzako1Z/\njO02hneB3XwF89Q/a7YIjSD5XA==\n-----END PRIVATE KEY-----"
        },
        "ES256": {
            "token": "eyJhbGciOiJFUzI1NiIsImtpZCI6ImVqZW1wbG8tZWMiLCJ0eXAiOiJKV1QifQ.eyJzdWIiOiJ1c3VhcmlvLTQyIiwibmFtZSI6IkFuYSBQXHUwMGU5cmV6IiwiZW1haWwiOiJhbmFAZWplbXBsby5jb20iLCJyb2xlIjoiYWRtaW4iLCJpc3MiOiJodHRwczovL2F1dGguZWplbXBsby5jb20iLCJhdWQiOiJhcGkuZWplbXBsby5jb20iLCJpYXQiOjE3NjcyMjU2MDAsImV4cCI6NDEwMjQ0NDgwMH0.lXPf7UYGjZPt7FnViqwhXZdpETRnJ4xk1Lvh-ZW1cEg_TmruEN5ouHv2S5eGiq_YbJ_Pk8RItErMO8-6ejWpsw",
            "keyType": "public_key",
            "key": "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEAeVqQ/FhAf7m/JEKE2uI9Nk2xHYT\nG1uUiwn4n13S/0s8//AJN2/ryVpYQpIvp8AulrJAsehr545MMgg7NSnmJw==\n-----END PUBLIC KEY-----",
            "privateKey": "-----BEGIN PRIVATE KEY-----\nMIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQg/po7RpjZbH4nHrhy\nB87rCwgr65sXBlDcJ8Q6AZWeofChRANCAAQB5WpD8WEB/ub8kQoTa4j02TbEdhMb\nW5SLCfifXdL/Szz/8Ak3b+vJWlhCki+nwC6WskCx6GvnjkwyCDs1KeYn\n-----END PRIVATE KEY-----"
        },
        "EdDSA": {
            "token": "eyJhbGciOiJFZERTQSIsImtpZCI6ImVqZW1wbG8tZWQiLCJ0eXAiOiJKV1QifQ.eyJzdWIiOiJ1c3VhcmlvLTQyIiwibmFtZSI6IkFuYSBQXHUwMGU5cmV6IiwiZW1haWwiOiJhbmFAZWplbXBsby5jb20iLCJyb2xlIjoiYWRtaW4iLCJpc3MiOiJodHRwczovL2F1dGguZWplbXBsby5jb20iLCJhdWQiOiJhcGkuZWplbXBsby5jb20iLCJpYXQiOjE3NjcyMjU2MDAsImV4cCI6NDEwMjQ0NDgwMH0.A-e62Z8AhAP64zInKMBExuuu136LfNrI5jAPF3XtlD-sre2MPFs3TPFrS70q4Q5npQHW7iaFWmPIc3Pd39UkDw",
            "keyType": "public_key",
            "key": "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEA/2msB9E74pGys9FQhgez2XQ1PhmPXTZws65bNOhAy6o=\n-----END PUBLIC KEY-----",
            "privateKey": "-----BEGIN PRIVATE KEY-----\nMC4CAQAwBQYDK2VwBCIEIAuY0dcBhqFSWio1W1WDXfLZSIxbkdYkcZGxQOsPlSle\n-----END PRIVATE KEY-----"
        }
    };

    var $ = function (id) { return document.getElementById(id); };

    // ── Utilidades ─────────────────────────────────────────────
    function b64urlToText(seg) {
        if (!/^[A-Za-z0-9_-]*$/.test(seg) || seg.length % 4 === 1) throw new Error('b64');
        var b64 = seg.replace(/-/g, '+').replace(/_/g, '/');
        b64 += '===='.slice(0, (4 - b64.length % 4) % 4);
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    }

    function cleanToken(t) {
        return t.trim().replace(/^Bearer\s+/i, '').replace(/\s+/g, '');
    }

    function localDecode(token) {
        var partes = token.split('.');
        if (partes.length === 5) return { error: 'Es un JWE (5 partes, token cifrado): solo se decodifican JWT firmados de 3 partes.' };
        if (partes.length !== 3) return { error: 'Un JWT debe tener 3 partes separadas por puntos (tiene ' + partes.length + ').', partes: partes };
        var r = { partes: partes };
        try { r.header = JSON.parse(b64urlToText(partes[0])); } catch (e) { r.error = 'El encabezado no es Base64URL con JSON válido.'; return r; }
        try { r.payload = JSON.parse(b64urlToText(partes[1])); } catch (e) { r.error = 'La carga útil no es Base64URL con JSON válido.'; return r; }
        if (!r.header || typeof r.header !== 'object' || Array.isArray(r.header)) r.error = 'El encabezado no es un objeto JSON.';
        return r;
    }

    function renderToken(el, partes) {
        el.textContent = '';
        var clases = ['gd-jwt-h', 'gd-jwt-p', 'gd-jwt-s'];
        partes.forEach(function (p, i) {
            if (i > 0) el.appendChild(document.createTextNode('.'));
            var span = document.createElement('span');
            span.className = clases[i] || 'gd-jwt-s';
            span.textContent = p;
            el.appendChild(span);
        });
    }

    function showAlert(el, type, text) {
        el.className = 'gd-alert gd-alert-' + type;
        el.textContent = text;
        el.hidden = false;
    }

    function setBusy(btn, busy) {
        if (busy) { btn.dataset.label = btn.textContent; btn.textContent = '⏳ Procesando…'; btn.disabled = true; }
        else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; }
    }

    function post(data) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        var ctrl = new AbortController();
        var timer = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);
        return fetch(ENDPOINT, {
            method: 'POST', body: body, credentials: 'same-origin', signal: ctrl.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, message: 'Respuesta inesperada del servidor (HTTP ' + res.status + ').' }; });
        }).catch(function (err) {
            return { ok: false, message: err.name === 'AbortError' ? 'El servidor ha tardado demasiado en responder.' : 'No se ha podido conectar con el servidor.' };
        }).finally(function () { clearTimeout(timer); });
    }

    function relativo(seg) {
        var abs = Math.abs(seg);
        var txt = abs < 60 ? abs + ' s'
            : abs < 3600 ? Math.round(abs / 60) + ' min'
            : abs < 86400 ? Math.round(abs / 3600) + ' h'
            : Math.round(abs / 86400) + ' días';
        return seg >= 0 ? 'dentro de ' + txt : 'hace ' + txt;
    }

    function copyText(text, btn) {
        var done = function () {
            var label = btn.textContent;
            btn.textContent = '✓ Copiado';
            setTimeout(function () { btn.textContent = label; }, 1600);
        };
        var fallback = function () {
            var ta = document.createElement('textarea');
            ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            if (ok) done();
        };
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback);
        else fallback();
    }

    // ── Pestañas ───────────────────────────────────────────────
    function showPanel(id) {
        document.querySelectorAll('#cardJwt .gd-tab').forEach(function (t) {
            var activa = t.dataset.panel === id;
            t.classList.toggle('active', activa);
            t.setAttribute('aria-selected', activa ? 'true' : 'false');
        });
        document.querySelectorAll('#cardJwt .gd-panel').forEach(function (p) { p.hidden = p.id !== id; });
    }
    document.querySelectorAll('#cardJwt .gd-tab').forEach(function (tab) {
        tab.addEventListener('click', function () { showPanel(tab.dataset.panel); });
    });

    // ══════════════════════════════════════════════════════════
    // Decodificar
    // ══════════════════════════════════════════════════════════
    var tokIn = $('jwtInput'), tokCount = $('jwtCount'), tokColor = $('jwtColored'), tokAlert = $('jwtAlert');
    var outHeader = $('jwtHeader'), outPayload = $('jwtPayload');
    var claimsBox = $('jwtClaims'), claimsBody = $('jwtClaimsBody'), warnList = $('jwtWarnings');
    var statusBadge = $('jwtTokenStatus'), sigBadge = $('jwtSigStatus');
    var algLabel = $('jwtAlg');
    var secretRow = $('verifySecretRow'), asymRow = $('verifyAsymRow');
    var secretIn = $('verifySecret'), secretB64 = $('verifySecretB64');
    var pubIn = $('verifyPublicKey'), jwksIn = $('verifyJwks');
    var modoPub = $('modePublicKey'), modoJwks = $('modeJwks');
    var verifyBtn = $('btnVerify');
    var exampleSel = $('jwtExample');
    var decodeTimer = null;
    var current = null;

    function resetVerify() {
        sigBadge.className = 'gd-sig gd-sig-none';
        sigBadge.textContent = 'Firma sin verificar';
    }

    function setKeyMode(alg) {
        var hmac = HMAC.test(alg || '');
        secretRow.hidden = !hmac;
        asymRow.hidden = hmac;
    }

    function setAsymMode(jwks) {
        modoPub.classList.toggle('active', !jwks);
        modoJwks.classList.toggle('active', jwks);
        modoPub.setAttribute('aria-pressed', !jwks);
        modoJwks.setAttribute('aria-pressed', jwks);
        $('verifyPublicKeyWrap').hidden = jwks;
        $('verifyJwksWrap').hidden = !jwks;
    }

    function renderClaims(data) {
        claimsBody.textContent = '';
        (data.claims || []).forEach(function (c) {
            var tr = document.createElement('tr');
            var td1 = document.createElement('td');
            var code = document.createElement('code');
            code.textContent = c.claim;
            td1.appendChild(code);
            var small = document.createElement('small');
            small.textContent = c.label;
            td1.appendChild(small);

            var td2 = document.createElement('td');
            td2.className = 'gd-claim-value';
            td2.textContent = typeof c.value === 'string' ? c.value : JSON.stringify(c.value);
            if (c.date) {
                var fecha = document.createElement('small');
                fecha.textContent = new Date(c.date).toLocaleString('es-ES') + ' · ' + relativo(c.relative);
                td2.appendChild(fecha);
            }
            var td3 = document.createElement('td');
            td3.textContent = c.description;

            tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
            claimsBody.appendChild(tr);
        });

        var estados = {
            valid: ['gd-sig gd-sig-ok', 'Vigente'],
            expired: ['gd-sig gd-sig-bad', 'Caducado'],
            not_yet_valid: ['gd-sig gd-sig-warn', 'Todavía no válido']
        };
        var e = estados[data.token_status] || estados.valid;
        statusBadge.className = e[0];
        statusBadge.textContent = e[1];

        warnList.textContent = '';
        (data.warnings || []).forEach(function (w) {
            var li = document.createElement('li');
            li.textContent = w;
            warnList.appendChild(li);
        });
        warnList.hidden = !(data.warnings || []).length;
        claimsBox.hidden = false;
    }

    function serverDecode(token) {
        post({ action: 'decode', token: token }).then(function (data) {
            if (cleanToken(tokIn.value) !== token) return;
            if (data.ok) renderClaims(data);
            else showAlert(tokAlert, 'error', data.message);
        });
    }

    function onTokenChange() {
        var token = cleanToken(tokIn.value);
        tokCount.textContent = token.length.toLocaleString('es-ES') + ' caracteres';
        tokAlert.hidden = true;
        claimsBox.hidden = true;
        resetVerify();
        clearTimeout(decodeTimer);

        if (!token) {
            tokColor.textContent = '';
            outHeader.textContent = outPayload.textContent = '';
            algLabel.textContent = '—';
            current = null;
            return;
        }
        var r = localDecode(token);
        renderToken(tokColor, r.partes || [token]);
        outHeader.textContent = r.header ? JSON.stringify(r.header, null, 2) : '';
        outPayload.textContent = r.payload ? JSON.stringify(r.payload, null, 2) : '';
        if (r.error) {
            showAlert(tokAlert, 'error', r.error);
            algLabel.textContent = '—';
            current = null;
            return;
        }
        current = r;
        algLabel.textContent = String(r.header.alg || '—');
        setKeyMode(r.header.alg);
        decodeTimer = setTimeout(function () { serverDecode(token); }, 600);
    }

    tokIn.addEventListener('input', onTokenChange);
    modoPub.addEventListener('click', function () { setAsymMode(false); });
    modoJwks.addEventListener('click', function () { setAsymMode(true); });

    verifyBtn.addEventListener('click', function () {
        var token = cleanToken(tokIn.value);
        if (!token || !current) { showAlert(tokAlert, 'error', 'Pega primero un JWT válido.'); return; }
        var datos = { action: 'verify', token: token };
        if (HMAC.test(current.header.alg || '')) {
            datos.key_type = 'secret';
            datos.secret = secretIn.value;
            datos.secret_b64 = secretB64.checked ? '1' : '0';
        } else if (!$('verifyJwksWrap').hidden) {
            datos.key_type = 'jwks';
            datos.jwks_uri = jwksIn.value.trim();
        } else {
            datos.key_type = 'public_key';
            datos.public_key = pubIn.value;
        }

        setBusy(verifyBtn, true);
        post(datos).then(function (data) {
            if (!data.ok) {
                sigBadge.className = 'gd-sig gd-sig-bad';
                sigBadge.textContent = 'No se pudo verificar';
                showAlert(tokAlert, 'error', data.message);
                return;
            }
            tokAlert.hidden = true;
            renderClaims(data);
            if (data.verified) {
                sigBadge.className = 'gd-sig gd-sig-ok';
                sigBadge.textContent = '✓ Firma verificada';
            } else {
                sigBadge.className = 'gd-sig gd-sig-bad';
                sigBadge.textContent = '✗ Firma no válida';
                showAlert(tokAlert, 'error', data.verify_message || 'La firma no coincide.');
            }
        }).finally(function () { setBusy(verifyBtn, false); });
    });

    exampleSel.addEventListener('change', function () {
        var ej = EJEMPLOS[exampleSel.value];
        exampleSel.value = '';
        if (!ej) return;
        showPanel('panelDecode');
        tokIn.value = ej.token;
        if (ej.keyType === 'secret') {
            secretIn.value = ej.key;
            secretB64.checked = false;
        } else {
            pubIn.value = ej.key;
            setAsymMode(false);
        }
        onTokenChange();
    });

    $('btnDecodeClear').addEventListener('click', function () {
        tokIn.value = secretIn.value = pubIn.value = jwksIn.value = '';
        onTokenChange();
        tokIn.focus();
    });

    // ══════════════════════════════════════════════════════════
    // Codificar
    // ══════════════════════════════════════════════════════════
    var encAlg = $('encAlg'), encHeader = $('encHeader'), encPayload = $('encPayload');
    var encHeaderMeta = $('encHeaderMeta'), encPayloadMeta = $('encPayloadMeta');
    var encSecretRow = $('encSecretRow'), encKeyRow = $('encKeyRow');
    var encSecret = $('encSecret'), encSecretB64 = $('encSecretB64'), encKey = $('encPrivateKey');
    var encBtn = $('btnEncode'), encAlert = $('encodeAlert'), encResult = $('encodeResult');
    var encToken = $('encToken'), encWarn = $('encWarnings');
    var lastToken = '';

    function parseObj(texto) {
        try {
            var v = JSON.parse(texto);
            return v && typeof v === 'object' && !Array.isArray(v) ? v : null;
        } catch (e) { return null; }
    }

    function validar(area, meta) {
        var ok = area.value.trim() === '' || parseObj(area.value) !== null;
        meta.textContent = ok ? 'JSON válido' : 'JSON no válido: revisa comas, comillas y llaves';
        meta.className = ok ? 'gd-json-ok' : 'gd-json-bad';
        return ok;
    }

    function syncAlg() {
        var alg = encAlg.value;
        var h = parseObj(encHeader.value);
        if (h) {
            h.alg = alg;
            if (!h.typ) h.typ = 'JWT';
            encHeader.value = JSON.stringify(h, null, 2);
        }
        var hmac = HMAC.test(alg);
        encSecretRow.hidden = !hmac;
        encKeyRow.hidden = hmac;
        var ej = EJEMPLOS[alg];
        encKey.placeholder = ej && ej.privateKey
            ? 'Pega aquí tu clave privada PEM (-----BEGIN PRIVATE KEY-----)… o usa «Cargar clave de ejemplo»'
            : 'Pega aquí tu clave privada PEM (-----BEGIN PRIVATE KEY-----)…';
        validar(encHeader, encHeaderMeta);
    }

    function tocarPayload(fn) {
        var p = parseObj(encPayload.value) || {};
        fn(p);
        encPayload.value = JSON.stringify(p, null, 2);
        validar(encPayload, encPayloadMeta);
    }

    encAlg.addEventListener('change', syncAlg);
    encHeader.addEventListener('input', function () { validar(encHeader, encHeaderMeta); });
    encPayload.addEventListener('input', function () { validar(encPayload, encPayloadMeta); });
    $('btnIatNow').addEventListener('click', function () { tocarPayload(function (p) { p.iat = Math.floor(Date.now() / 1000); }); });
    $('btnExp1h').addEventListener('click', function () { tocarPayload(function (p) { p.exp = Math.floor(Date.now() / 1000) + 3600; }); });
    $('btnExp24h').addEventListener('click', function () { tocarPayload(function (p) { p.exp = Math.floor(Date.now() / 1000) + 86400; }); });
    $('btnEncKeyExample').addEventListener('click', function () {
        var alg = encAlg.value;
        var ej = EJEMPLOS[alg] || EJEMPLOS[{ RS384: 'RS256', RS512: 'RS256' }[alg]];
        if (HMAC.test(alg)) { encSecret.value = EJEMPLOS.HS256.key; encSecretB64.checked = false; return; }
        if (ej && ej.privateKey) encKey.value = ej.privateKey;
        else showAlert(encAlert, 'warn', 'No hay clave de ejemplo para ' + alg + ': genera una con openssl (ver la documentación más abajo).');
    });

    encBtn.addEventListener('click', function () {
        encAlert.hidden = true;
        if (!validar(encHeader, encHeaderMeta) || !validar(encPayload, encPayloadMeta)) {
            showAlert(encAlert, 'error', 'Corrige el JSON del encabezado o de la carga útil antes de generar el token.');
            return;
        }
        var alg = encAlg.value;
        var datos = { action: 'encode', alg: alg, header: encHeader.value, payload: encPayload.value };
        if (HMAC.test(alg)) {
            datos.key_type = 'secret';
            datos.secret = encSecret.value;
            datos.secret_b64 = encSecretB64.checked ? '1' : '0';
        } else {
            datos.key_type = 'private_key';
            datos.private_key = encKey.value;
        }

        setBusy(encBtn, true);
        post(datos).then(function (data) {
            if (!data.ok) { encResult.hidden = true; showAlert(encAlert, 'error', data.message); return; }
            lastToken = data.token;
            renderToken(encToken, data.parts);
            encWarn.textContent = '';
            (data.warnings || []).forEach(function (w) {
                var li = document.createElement('li');
                li.textContent = w;
                encWarn.appendChild(li);
            });
            encWarn.hidden = !(data.warnings || []).length;
            encResult.hidden = false;
        }).finally(function () { setBusy(encBtn, false); });
    });

    $('btnCopyJwt').addEventListener('click', function () { if (lastToken) copyText(lastToken, $('btnCopyJwt')); });
    $('btnSendToDecoder').addEventListener('click', function () {
        if (!lastToken) return;
        var alg = encAlg.value;
        tokIn.value = lastToken;
        if (HMAC.test(alg)) {
            secretIn.value = encSecret.value;
            secretB64.checked = encSecretB64.checked;
        } else {
            var ej = EJEMPLOS[alg];
            pubIn.value = ej && ej.privateKey === encKey.value.trim() ? ej.key : '';
            setAsymMode(false);
        }
        showPanel('panelDecode');
        onTokenChange();
        tokIn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // Las claves no deben quedarse en la página al salir (ni en la caché de ida y vuelta).
    window.addEventListener('pagehide', function () {
        secretIn.value = pubIn.value = encSecret.value = encKey.value = '';
    });

    setAsymMode(false);
    resetVerify();
    syncAlg();
    validar(encPayload, encPayloadMeta);
})();
