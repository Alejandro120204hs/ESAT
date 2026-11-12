/* =========================================================
   ESAT — Panel Docente: datos del servidor y guardado
   - Lee window.DOC_SERVIDOR, que cada vista pone con @json (PanelDocenteService::datos): los grupos que
     el docente tiene a cargo, con horario, módulos (fechas calculadas por CalificacionService) y
     estudiantes, más la asistencia y las notas ya registradas.
   - Deriva lo que muestran las pantallas con las mismas reglas de AsistenciaService y
     CalificacionService (clases dictadas, % de asistencia, estados de nota) y lo vuelve a calcular
     después de cada guardado, sin recargar la página.
   - Guarda con fetch a docente.asistencia.guardar y docente.notas.guardar; el servidor valida lo mismo
     (AsistenciaRequest, NotasRequest) y responde con el mensaje para el docente.

   Lo que expone:
     DOC_DATOS.hoy            'AAAA-MM-DD'
     DOC_DATOS.grupos[]       { id, codigo, nombre, programa, escuela, jornada, estado, soloConsulta,
                                inicio, fin, cupo, horario[{dia, diaIso, ini, fin}],
                                modulos[{id, nombre, horas, ini, fin, estado}], moduloActual,
                                clases[{fecha, dia, ini, fin, estado}], sinRegistrar['AAAA-MM-DD'],
                                estudiantes[...], activos, graduados, pct, bajoMinimo,
                                modulosSinNota[{modulo, faltan}], porHabilitar }
       estudiante:            { id, matriculaId, nombres, apellidos, tipoDoc, documento, edad, menor, telefono,
                                acudiente|null, matricula{codigo, fecha, estado, estadoFecha},
                                desde, hasta, asistencia{P, T, A, E, pct, situacion},
                                notas{moduloId: {n, h, estado}}, promedio }
       clase.estado:          registrada · sin_registrar · hoy (todavía no termina) · futura
       nota.estado:           aprobado · habilito · por_habilitar · reprobado · sin_nota · no_cursado · por_iniciar
     DOC_DATOS.planilla(grupo, fecha)  estudiantes que deben aparecer en la lista de esa clase
     DOC_DATOS.registros(grupoId, fecha)  { estudianteId: {estado, motivo} } o null
     DOC_DATOS.estadoNota(n, h) · notaFinal(n, h) · porcentaje(asistio, falto) · situacion(pct)
     DOC_DATOS.APRUEBA (3.0) · MINIMO (80) · ESTADOS_ASISTENCIA
     DOC_API.guardarAsistencia(grupoId, fecha, {estId: {estado, motivo}})  → Promise {ok, mensaje}
     DOC_API.guardarNotas(grupoId, moduloId, {estId: {n, h}})               → Promise {ok, mensaje}
   ========================================================= */
(function () {
    'use strict';

    var S = window.DOC_SERVIDOR || { hoy: '', grupos: [], registros: {}, notas: {}, rutas: {} };
    var APRUEBA = 3.0;
    var MINIMO  = 80;
    var ESTADOS_ASISTENCIA = ['presente', 'tarde', 'ausente', 'excusa'];

    /* ── Fechas 'AAAA-MM-DD' (hoy es el del servidor: hora de Colombia) ── */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function masDias(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    function diaIso(d) { var w = d.getDay(); return w === 0 ? 7 : w; }
    function minutos(hhmm) { var p = hhmm.split(':'); return +p[0] * 60 + +p[1]; }
    function minAhora() { var a = new Date(); return a.getHours() * 60 + a.getMinutes(); }

    var hoyStr = S.hoy || ymd(new Date());

    /* ── Lo registrado: asistencia por grupo y fecha, notas por grupo y módulo ── */
    var base = { registros: S.registros || {}, notas: S.notas || {} };

    // Días de clase entre el inicio y el fin del grupo, cada uno con el horario que regía ese día (un cambio de horario
    // no mueve lo ya dictado; AsistenciaService::fechasDictadas y Curso::sesionEn)
    function clasesDe(g) {
        var hist = g.historial || g.horario, out = [];
        for (var d = parse(g.inicio); ymd(d) < g.fin; d = masDias(d, 1)) {
            var f = ymd(d), iso = diaIso(d);
            var s = hist.find(function (x) { return x.diaIso === iso && (!x.desde || x.desde <= f) && (!x.hasta || x.hasta >= f); });
            if (s) out.push({ fecha: f, dia: s.dia, ini: s.ini, fin: s.fin });
        }
        return out;
    }
    // Ya dictada: antes de hoy, o hoy si la sesión ya terminó
    function dictada(c) { return c.fecha < hoyStr || (c.fecha === hoyStr && minAhora() >= minutos(c.fin)); }

    var grupos = (S.grupos || []).map(function (g) {
        g._clases = clasesDe(g);
        return g;
    });

    /* ── Reglas (las mismas del backend) ── */
    function porcentaje(asistio, falto) { var t = asistio + falto; return t ? Math.round(asistio * 100 / t) : null; }
    function situacion(pct) { return pct === null ? 'sin_datos' : pct >= 90 ? 'bien' : pct >= MINIMO ? 'vigilar' : 'fallas'; }
    function estadoNota(n, h) {
        if (n === null || n === undefined) return 'sin_nota';
        if (n >= APRUEBA) return 'aprobado';
        if (h === null || h === undefined) return 'por_habilitar';
        return h >= APRUEBA ? 'habilito' : 'reprobado';
    }
    // La que cuenta: la de la habilitación si la presentó, si no la nota (Calificacion::notaFinal)
    function notaFinal(n, h) { return h === null || h === undefined ? n : h; }

    function registrosDe(gid, fecha) { return (base.registros[gid] && base.registros[gid][fecha]) || null; }
    function notasDe(gid, mid) { return (base.notas[gid] && base.notas[gid][mid]) || {}; }
    function enVentana(e, fecha) { return fecha >= e.desde && !(e.hasta && fecha >= e.hasta); }

    /* ── Lo que se deriva (se recalcula después de cada guardado) ── */
    function calcular() {
        grupos.forEach(function (g) {
            var hechas = g._clases.filter(dictada);
            g.moduloActual = g.modulos.filter(function (m) { return m.estado === 'en_curso'; })[0] || null;
            g.clases = g._clases.map(function (c) {
                var reg = registrosDe(g.id, c.fecha);
                var estado = reg ? 'registrada' : dictada(c) ? 'sin_registrar' : c.fecha === hoyStr ? 'hoy' : 'futura';
                return { fecha: c.fecha, dia: c.dia, ini: c.ini, fin: c.fin, estado: estado };
            });
            g.sinRegistrar = g.soloConsulta ? [] : g.clases.filter(function (c) { return c.estado === 'sin_registrar'; }).map(function (c) { return c.fecha; });

            var sumA = 0, sumF = 0;
            g.activos = 0; g.graduados = 0; g.bajoMinimo = 0; g.porHabilitar = 0;
            g.estudiantes.forEach(function (e) {
                var a = { P: 0, T: 0, A: 0, E: 0 };
                hechas.forEach(function (c) {
                    if (!enVentana(e, c.fecha)) return;
                    var reg = registrosDe(g.id, c.fecha), x = reg && reg[e.id];
                    if (x) a[{ presente: 'P', tarde: 'T', ausente: 'A', excusa: 'E' }[x.estado]]++;
                });
                a.pct = porcentaje(a.P + a.T, a.A);
                a.situacion = situacion(a.pct);
                e.asistencia = a;

                e.notas = {};
                var finales = [];
                g.modulos.forEach(function (m) {
                    var x = notasDe(g.id, m.id)[e.id];
                    var tiene = x && x.n !== null && x.n !== undefined;
                    var estado;
                    // Terminó antes de su matrícula: no lo cursó en este grupo (CalificacionService)
                    if (m.fin < e.matricula.fecha) estado = 'no_cursado';
                    else if (tiene) estado = estadoNota(x.n, x.h);
                    else estado = m.estado === 'por_iniciar' ? 'por_iniciar' : 'sin_nota';
                    e.notas[m.id] = { n: tiene ? x.n : null, h: tiene ? x.h : null, estado: estado };
                    if (tiene) finales.push(notaFinal(x.n, x.h));
                    // La habilitación es después de que el módulo termina: uno en curso todavía no cuenta
                    if (estado === 'por_habilitar' && m.estado === 'terminado' && e.matricula.estado === 'activo') g.porHabilitar++;
                });
                e.promedio = finales.length ? Math.round(finales.reduce(function (s, n) { return s + n; }, 0) / finales.length * 10) / 10 : null;

                if (e.matricula.estado === 'activo') g.activos++;
                if (e.matricula.estado === 'graduado') g.graduados++;
                // El % del grupo cuenta a quienes siguen (o terminaron) el programa, no a aplazados ni retirados
                if (e.matricula.estado === 'activo' || e.matricula.estado === 'graduado') {
                    sumA += a.P + a.T; sumF += a.A;
                    if (a.situacion === 'fallas') g.bajoMinimo++;
                }
            });
            g.pct = porcentaje(sumA, sumF);

            // Módulos ya terminados en los que a algún estudiante activo le falta la nota
            g.modulosSinNota = g.soloConsulta ? [] : g.modulos.filter(function (m) { return m.estado === 'terminado'; }).map(function (m) {
                var faltan = g.estudiantes.filter(function (e) { return e.matricula.estado === 'activo' && e.notas[m.id].estado === 'sin_nota'; }).length;
                return { modulo: m, faltan: faltan };
            }).filter(function (x) { return x.faltan > 0; });
        });
    }
    calcular();

    function planilla(g, fecha) {
        return g.estudiantes.filter(function (e) { return enVentana(e, fecha); });
    }

    /* ── Envío al servidor ── */
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function enviar(url, cuerpo) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(cuerpo)
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (r.ok) return j;
                var msg;
                if (r.status === 422 && j.errors) msg = j.errors[Object.keys(j.errors)[0]][0];
                else if (r.status === 401) msg = 'Tu sesión se cerró. Vuelve a ingresar para guardar.';
                else if (r.status === 419) msg = 'La página estuvo abierta mucho tiempo. Recárgala y vuelve a guardar.';
                else if (r.status === 403) msg = j.message || 'Ese grupo no es tuyo.';
                throw new Error(msg || 'No se pudo guardar. Intenta de nuevo.');
            });
        }, function () {
            throw new Error('No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.');
        });
    }
    function ruta(plantilla, valores) {
        return plantilla.replace(/__(\w+)__/g, function (_, k) { return encodeURIComponent(valores[k]); });
    }

    window.DOC_DATOS = {
        hoy: hoyStr,
        grupos: grupos,
        APRUEBA: APRUEBA,
        MINIMO: MINIMO,
        ESTADOS_ASISTENCIA: ESTADOS_ASISTENCIA,
        planilla: planilla,
        registros: registrosDe,
        estadoNota: estadoNota,
        notaFinal: notaFinal,
        porcentaje: porcentaje,
        situacion: situacion
    };

    window.DOC_API = {
        /* registros: { estudianteId: {estado: 'presente'|'tarde'|'ausente'|'excusa', motivo} } */
        guardarAsistencia: function (grupoId, fecha, registros) {
            return enviar(ruta(S.rutas.asistencia, { GRUPO: grupoId }), { fecha: fecha, registros: registros }).then(function (r) {
                var limpio = {};
                Object.keys(registros).forEach(function (id) {
                    var x = registros[id];
                    limpio[id] = { estado: x.estado, motivo: x.estado === 'excusa' ? String(x.motivo || '').trim() : null };
                });
                base.registros[grupoId] = base.registros[grupoId] || {};
                base.registros[grupoId][fecha] = limpio;
                calcular();
                return r;
            });
        },

        /* notas: { estudianteId: {n: número|null, h: número|null} } — solo las que cambiaron */
        guardarNotas: function (grupoId, moduloId, notas) {
            return enviar(ruta(S.rutas.notas, { GRUPO: grupoId, MODULO: moduloId }), { notas: notas }).then(function (r) {
                base.notas[grupoId] = base.notas[grupoId] || {};
                var delModulo = base.notas[grupoId][moduloId] = base.notas[grupoId][moduloId] || {};
                Object.keys(notas).forEach(function (id) {
                    var n = notas[id].n, h = notas[id].h;
                    if (n === null || n === undefined || n === '') delete delModulo[id];
                    else delModulo[id] = { n: n, h: h === '' || h === undefined ? null : h };
                });
                calcular();
                return r;
            });
        }
    };
})();
