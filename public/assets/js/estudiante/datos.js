/* =========================================================
   ESAT — Panel Estudiante: datos del servidor y guardado
   - Lee window.EST_SERVIDOR.datos (PanelEstudianteService::datos, que usa las reglas de AsistenciaService,
     CalificacionService, PagoService, ComunicadoService y ForoService) y lo deja en EST_DATOS.
   - EST_API guarda con fetch (CSRF); el servidor valida lo mismo y responde con los datos recalculados, que se copian
     sobre el mismo EST_DATOS (las pantallas guardan una referencia a él y vuelven a pintar).

   Lo que expone:
     EST_DATOS.hoy 'AAAA-MM-DD' · estudiante {nombres, apellidos, sede, documento}
     EST_DATOS.matricula   null (sin matrícula) o {id, codigo, fecha, estado, estadoFecha, programa, escuela, nivel,
                            modalidad, duracionMeses, tipoPeriodo, grupo{nombre, codigo, jornada, estado, inicio, fin,
                            docente}|null, horario[{diaIso, dia, ini, fin}]}
     EST_DATOS.modulos[]   {id, numero, nombre, horas, ini, fin, estado (terminado|en_curso|por_iniciar), n, h,
                            fechaNota, fechaHab, nota (la que cuenta), estadoNota (aprobado|habilito|por_habilitar|
                            reprobado|sin_nota|no_cursado|en_curso|por_iniciar)}
     EST_DATOS.moduloActual · promedio · aprobados
     EST_DATOS.clases[]    {fecha, dia, ini, fin, moduloId, estado (P|T|A|E|sin_registrar|hoy|futura), motivo}
     EST_DATOS.asistencia  {P, T, A, E, sinRegistrar, pct, situacion}
     EST_DATOS.evaluaciones[] {id, moduloId, titulo, descripcion, abre, cierra, cerrada, preguntas[{id, enunciado,
                               opciones[{id, texto}], correcta (solo si ya cerró)}], respuesta|null {fecha, marcadas,
                               aciertos, nota}}
     EST_DATOS.actividades[]  {id, moduloId, titulo, instrucciones, abre, cierra, cerrada,
                               entrega|null {archivo{nombre, tamano, tipo, url}, fecha, nota, comentario, fechaNota}}
     EST_DATOS.pagos       null o {plan, puedeElegir, precio, derecho, cuotaSistema, descuentoPct, opciones{mensual,
                            periodo}, vencidasSiElige(plan), cobros[{id, tipo, numero, total, concepto, vence|null,
                            valor, valorPrograma, sistema, abonado, restante, estado}], recibos[{numero, fecha, medio,
                            referencia, total, aplicado[{concepto, monto}], anulado, motivo}], resumen{vencido, aPagar,
                            pagado, porPagar, proxima}, fmt(n)}
     EST_DATOS.comunicados[]  {id, titulo, cuerpo, de{nombre, rol}, para, fecha, fijado, urgente, leido, adjunto|null}
     EST_DATOS.foro        {categorias[], claves{nombre: clave}, temas[{id, categoria, titulo, fijado, cerrado, ultimo,
                            mio, mensajes[{id, autor{nombre, rol (e|d|a|x), yo}, fecha, texto, adjunto|null, reportado}]}]}
     EST_DATOS.reglas      {APRUEBA, MINIMO, estadoNota, notaFinal, porcentaje, situacion, notaEvaluacion}
     EST_DATOS.limites     {archivo (10 MB), adjunto (5 MB)}
     EST_API.elegirPlan(plan) · responderEvaluacion(id, {preguntaId: opcionId}) · entregarActividad(id, File)
     EST_API.marcarLeido(id) · abrirTema({categoria, titulo, texto, adjunto: File|null})
     EST_API.responderTema(id, {texto, adjunto: File|null}) · reportar(mensajeId, motivo)
       → Promise {ok, mensaje[, tema]}
   ========================================================= */
(function () {
    'use strict';

    var S = window.EST_SERVIDOR || {};
    var RUTAS = S.rutas || {};
    var APRUEBA = 3.0;
    var MINIMO = 80;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    function porcentaje(asistio, falto) { var t = asistio + falto; return t ? Math.round(asistio * 100 / t) : null; }
    function situacion(pct) { return pct === null ? 'sin_datos' : pct >= 90 ? 'bien' : pct >= MINIMO ? 'vigilar' : 'fallas'; }
    function estadoNota(n, h) {
        if (n === null || n === undefined) return 'sin_nota';
        if (n >= APRUEBA) return 'aprobado';
        if (h === null || h === undefined) return 'por_habilitar';
        return h >= APRUEBA ? 'habilito' : 'reprobado';
    }
    function notaFinal(n, h) { return h === null || h === undefined ? n : h; }
    // Evaluación de selección múltiple: 5,0 × aciertos ÷ preguntas, mínimo 1,0, con un decimal (Evaluacion::nota)
    function notaEvaluacion(aciertos, total) { return total ? Math.max(1, Math.round(50 * aciertos / total) / 10) : null; }
    function fmt(n) { return '$ ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    var D = window.EST_DATOS = {};

    /* Copia los datos del servidor sobre el mismo objeto y agrega lo que no viaja en JSON */
    function poner(datos) {
        Object.keys(D).forEach(function (k) { delete D[k]; });
        Object.keys(datos || {}).forEach(function (k) { D[k] = datos[k]; });
        D.modulos = D.modulos || [];
        D.clases = D.clases || [];
        D.evaluaciones = D.evaluaciones || [];
        D.actividades = D.actividades || [];
        D.comunicados = D.comunicados || [];
        D.foro = D.foro || { categorias: [], claves: {}, temas: [] };
        D.asistencia = D.asistencia || { P: 0, T: 0, A: 0, E: 0, sinRegistrar: 0, pct: null, situacion: 'sin_datos' };
        D.reglas = { APRUEBA: APRUEBA, MINIMO: MINIMO, estadoNota: estadoNota, notaFinal: notaFinal, porcentaje: porcentaje, situacion: situacion, notaEvaluacion: notaEvaluacion };
        D.limites = { archivo: 10 * 1024 * 1024, adjunto: 5 * 1024 * 1024 };
        if (D.pagos) {
            var vencidas = D.pagos.vencidasSiElige || {};
            D.pagos.vencidasSiElige = function (plan) { return vencidas[plan] || 0; };
            D.pagos.fmt = fmt;
        }
    }
    poner(S.datos);

    /* ── Guardar ─────────────────────────────── */
    function ruta(nombre, id) { return String(RUTAS[nombre] || '').replace('__ID__', encodeURIComponent(id)); }

    // cuerpo: objeto (va como JSON) o FormData (archivos). Resuelve {ok, mensaje, ...}; nunca rechaza.
    function enviar(url, cuerpo) {
        var esForm = typeof FormData !== 'undefined' && cuerpo instanceof FormData;
        var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (!esForm) headers['Content-Type'] = 'application/json';
        return fetch(url, { method: 'POST', headers: headers, body: esForm ? cuerpo : JSON.stringify(cuerpo || {}), credentials: 'same-origin' })
            .then(function (r) {
                // Sesión vencida o cuenta desactivada: el login dice el motivo
                if (r.status === 401 || r.status === 419) { window.location.reload(); return { ok: false, mensaje: 'Tu sesión terminó. Vuelve a ingresar.' }; }
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (r.ok && j.ok) {
                        if (j.datos) poner(j.datos);
                        return j;
                    }
                    var errores = j.errors || {};
                    var k = Object.keys(errores)[0];
                    var mensaje = k ? errores[k][0]
                        : (r.status === 403 ? (j.message && j.message !== 'This action is unauthorized.' ? j.message : 'No tienes permiso para hacer esto.')
                        : r.status === 404 ? 'Ya no existe. Recarga la página.'
                        : r.status === 413 ? 'El archivo pesa demasiado.'
                        : 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.');
                    return { ok: false, mensaje: mensaje };
                });
            })
            .catch(function () { return { ok: false, mensaje: 'No se pudo guardar. Revisa tu conexión e intenta de nuevo.' }; });
    }
    function form(campos) {
        var f = new FormData();
        Object.keys(campos).forEach(function (k) { if (campos[k] !== null && campos[k] !== undefined) f.append(k, campos[k]); });
        return f;
    }

    window.EST_API = {
        elegirPlan: function (plan) { return enviar(RUTAS.plan, { plan: plan }); },
        responderEvaluacion: function (id, marcadas) { return enviar(ruta('responder', id), { respuestas: marcadas }); },
        entregarActividad: function (id, archivo) { return enviar(ruta('entregar', id), form({ archivo: archivo })); },
        marcarLeido: function (id) { return enviar(ruta('leido', id), {}); },
        abrirTema: function (t) {
            return enviar(RUTAS.tema, form({ categoria: (D.foro.claves || {})[t.categoria] || t.categoria, titulo: t.titulo, texto: t.texto, adjunto: t.adjunto || null }));
        },
        responderTema: function (id, m) { return enviar(ruta('responderTema', id), form({ texto: m.texto, adjunto: m.adjunto || null })); },
        reportar: function (mensajeId, motivo) { return enviar(ruta('reportar', mensajeId), { motivo: motivo }); }
    };
})();
