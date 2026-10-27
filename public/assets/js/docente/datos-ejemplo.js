/* =========================================================
   ESAT — Panel Docente: DATOS DE EJEMPLO (mock, solo frontend)
   - Programas, escuelas y jornadas son reales; grupos, módulos, estudiantes, asistencias y
     notas son FICTICIOS. Las fechas se arman alrededor de hoy para que siempre haya clases
     de hoy, pendientes, un módulo en curso y un grupo ya finalizado.
   - Simula lo que mandará el servidor cuando se conecte el backend, con las mismas reglas de
     AsistenciaService y CalificacionService (clases dictadas, % de asistencia, fechas de los
     módulos, estados de nota).
   - DOC_API guarda en sessionStorage para que las pantallas se vean coherentes entre sí
     mientras no hay persistencia real. Al conectar el backend: cambiar DOC_API por fetch()
     a las rutas reales y DOC_DATOS por los datos del controlador.

   Lo que expone:
     DOC_DATOS.hoy            'AAAA-MM-DD'
     DOC_DATOS.grupos[]       { id, codigo, nombre, programa, escuela, jornada, estado, soloConsulta,
                                inicio, fin, cupo, horario[{dia, diaIso, ini, fin}],
                                modulos[{id, nombre, horas, ini, fin, estado}], moduloActual,
                                clases[{fecha, dia, ini, fin, estado}], sinRegistrar['AAAA-MM-DD'],
                                estudiantes[...], activos, graduados, pct, bajoMinimo,
                                modulosSinNota[{modulo, faltan}], porHabilitar }
       estudiante:            { id, nombres, apellidos, tipoDoc, documento, edad, menor, telefono,
                                acudiente|null, matricula{codigo, fecha, estado, estadoFecha},
                                desde, hasta, asistencia{P, T, A, E, pct, situacion},
                                notas{moduloId: {n, h, estado}}, promedio }
       clase.estado:          registrada · sin_registrar · hoy (todavía no termina) · futura
       nota.estado:           aprobado · habilito · por_habilitar · reprobado · sin_nota · no_cursado
     DOC_DATOS.planilla(grupo, fecha)  estudiantes que deben aparecer en la lista de esa clase
     DOC_DATOS.registros(grupoId, fecha)  { estudianteId: {estado, motivo} } o null
     DOC_DATOS.estadoNota(n, h) · notaFinal(n, h) · porcentaje(asistio, falto) · situacion(pct)
     DOC_DATOS.APRUEBA (3.0) · MINIMO (80) · ESTADOS_ASISTENCIA
     DOC_API.guardarAsistencia(grupoId, fecha, {estId: {estado, motivo}})  → Promise
     DOC_API.guardarNotas(grupoId, moduloId, {estId: {n, h}})               → Promise
     DOC_API.reiniciar()  borra lo guardado en esta pestaña
   ========================================================= */
(function () {
    'use strict';

    var CLAVE   = 'esat.docente.mock.v1';
    var DIAS    = { 'Lunes': 1, 'Martes': 2, 'Miércoles': 3, 'Jueves': 4, 'Viernes': 5, 'Sábado': 6 };
    var APRUEBA = 3.0;
    var MINIMO  = 80;
    var ESTADOS_ASISTENCIA = ['presente', 'tarde', 'ausente', 'excusa'];
    var MOTIVOS = ['Cita médica', 'Incapacidad médica', 'Calamidad doméstica', 'Diligencia personal con soporte'];

    /* ── Fechas locales 'AAAA-MM-DD' ── */
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function masDias(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
    // Igual que Carbon::addMonthsNoOverflow (Curso::finPara)
    function masMeses(d, n) {
        var x = new Date(d.getFullYear(), d.getMonth() + n, 1);
        var ultimo = new Date(x.getFullYear(), x.getMonth() + 1, 0).getDate();
        x.setDate(Math.min(d.getDate(), ultimo));
        return x;
    }
    function diaIso(d) { var w = d.getDay(); return w === 0 ? 7 : w; }
    function minutos(hhmm) { var p = hhmm.split(':'); return +p[0] * 60 + +p[1]; }
    function edadDe(nac) {
        var n = parse(nac), e = hoy.getFullYear() - n.getFullYear();
        if (hoy.getMonth() < n.getMonth() || (hoy.getMonth() === n.getMonth() && hoy.getDate() < n.getDate())) e--;
        return e;
    }

    var ahora    = new Date();
    var hoy      = new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate());
    var hoyStr   = ymd(hoy);
    var minAhora = ahora.getHours() * 60 + ahora.getMinutes();

    /* ── Azar con semilla: los mismos datos en cada recarga ── */
    function azar(semilla) {
        var h = 1779033703;
        for (var i = 0; i < semilla.length; i++) { h = Math.imul(h ^ semilla.charCodeAt(i), 3432918353); h = (h << 13) | (h >>> 19); }
        return function () {
            h = Math.imul(h ^ (h >>> 16), 2246822507); h = Math.imul(h ^ (h >>> 13), 3266489909);
            var t = (h ^= h >>> 16) >>> 0;
            return t / 4294967296;
        };
    }

    /* ── Grupos del docente (programas y escuelas reales; lo demás ficticio) ── */
    // inicio: la primera clase en o después de (hoy − mesesAtras meses + ajusteDias)
    var BASE = [
        {
            id: 101, codigo: 'CUR-ADM-014-A', nombre: 'Grupo A', programa: 'Auxiliar Contable y Administrativo',
            escuela: 'Escuela Administrativa', jornada: 'Mañana', duracion: 12, cupo: 25, mesesAtras: 5, ajusteDias: 0, estado: 'activo',
            horario: [['Lunes', '07:00', '10:00'], ['Miércoles', '07:00', '10:00'], ['Viernes', '07:00', '10:00']],
            modulos: [['Fundamentos de contabilidad', 120], ['Documentos comerciales y soportes', 100], ['Nómina y prestaciones sociales', 110],
                      ['Impuestos y obligaciones tributarias', 110], ['Herramientas ofimáticas para la gestión', 90], ['Atención al cliente y archivo', 70]],
            sinRegistrar: [0, 1],          // índices desde la última clase dictada
            moduloSinNotas: true,          // el último módulo terminado todavía no tiene notas
            estudiantes: [
                ['Laura Valentina', 'Ruiz Moreno', 22], ['Juan Sebastián', 'Gómez Pardo', 24], ['María Fernanda', 'Rojas Díaz', 31],
                ['Andrés Felipe', 'Castro León', 17, ['Martha León', 'Madre']], ['Daniela Alejandra', 'Torres Vega', 19],
                ['Carlos Eduardo', 'Ramírez Soto', 40], ['Natalia', 'Herrera Quintero', 27, null, { faltas: true }],
                ['Kevin Santiago', 'Mora Pinzón', 16, ['Jorge Mora', 'Padre']], ['Paula Andrea', 'Cárdenas Rincón', 35],
                ['Miguel Ángel', 'Suárez Ortiz', 21, null, { tarde: 85 }], ['Yuliana Marcela', 'Peña Ríos', 23, null, { aplazado: 21 }],
                ['Diego Alejandro', 'Vargas Melo', 29, null, { nivelBajo: true }]
            ]
        },
        {
            id: 102, codigo: 'CUR-SAL-006-B', nombre: 'Grupo B', programa: 'Servicios Geriátricos',
            escuela: 'Escuela de Salud', jornada: 'Noche', duracion: 10, cupo: 20, mesesAtras: 2, ajusteDias: -6, estado: 'activo',
            horario: [['Martes', '18:30', '21:30'], ['Jueves', '18:30', '21:30']],
            modulos: [['Fundamentos del envejecimiento', 60], ['Primeros auxilios y signos vitales', 80], ['Cuidado y movilización del adulto mayor', 100],
                      ['Nutrición en el adulto mayor', 60], ['Actividad física y recreación', 50], ['Ética y trato digno', 40]],
            sinRegistrar: [4],
            faltaUnaNota: true,            // a un estudiante le falta la nota del último módulo terminado
            estudiantes: [
                ['Sandra Milena', 'López Acosta', 38], ['Gloria Patricia', 'Mendoza Ruiz', 45], ['Jhon Fredy', 'Ortiz Calderón', 33, null, { faltas: true }],
                ['Luz Dary', 'Guzmán Pérez', 52], ['Angie Lorena', 'Beltrán Cruz', 20, null, { nivelBajo: true }], ['Wilson Andrés', 'Prieto Galindo', 28],
                ['Claudia Inés', 'Salazar Mejía', 41], ['Brayan Steven', 'Rodríguez Niño', 17, ['Ana Niño', 'Madre']], ['Marisol', 'Cortés Urrego', 36]
            ]
        },
        {
            id: 103, codigo: 'CUR-CIE-003-A', nombre: 'Grupo A', programa: 'Electricista',
            escuela: 'Escuela Ciencias', jornada: 'Fines de semana', duracion: 12, cupo: 20, mesesAtras: 11, ajusteDias: -12, estado: 'activo',
            horario: [['Sábado', '07:00', '13:00']],
            modulos: [['Circuitos eléctricos básicos', 80], ['Normatividad RETIE', 50], ['Instalaciones residenciales', 90],
                      ['Instalaciones comerciales', 70], ['Tableros y protecciones', 60], ['Mantenimiento eléctrico', 50]],
            sinRegistrar: [],
            moduloSinNotas: true,
            estudiantes: [
                ['Jorge Iván', 'Patiño Rivera', 34], ['Luis Alberto', 'Quiroga Sánchez', 46], ['Édgar Mauricio', 'Bernal Torres', 39],
                ['Cristian Camilo', 'Arias Moreno', 22, null, { nivelBajo: true }], ['Óscar Javier', 'Medina Lozano', 30, null, { faltas: true }],
                ['Fabián Ricardo', 'Chaves Duarte', 27], ['Sergio Esteban', 'Molina Parra', 19], ['Hernán Darío', 'Castaño Gil', 51],
                ['Julián David', 'Ospina Rueda', 17, ['Rosa Rueda', 'Abuela']], ['Camilo Andrés', 'Fajardo Ruiz', 25, null, { retirado: 120 }]
            ]
        },
        {
            id: 104, codigo: 'CUR-ADM-009-C', nombre: 'Grupo C', programa: 'Agente de Tránsito',
            escuela: 'Escuela Administrativa', jornada: 'Tarde', duracion: 6, cupo: 25, mesesAtras: 8, ajusteDias: 0, estado: 'finalizado',
            horario: [['Lunes', '14:00', '17:00'], ['Miércoles', '14:00', '17:00']],
            modulos: [['Normas de tránsito y transporte', 80], ['Señalización vial', 50], ['Procedimientos de control', 70], ['Primer respondiente en vía', 40]],
            sinRegistrar: [],
            estudiantes: [
                ['Leidy Johana', 'Martínez Ávila', 24], ['Ronald Esteban', 'Piza Jaimes', 26], ['Karen Viviana', 'Muñoz Soler', 29],
                ['Edwin Fernando', 'Acero Puentes', 32], ['Tatiana', 'Ruiz Bermúdez', 21], ['Fredy Alonso', 'Cuervo Lara', 37, null, { retirado: 150 }],
                ['Mónica Alejandra', 'Pulido Vera', 30], ['Nicolás', 'Hurtado Cano', 23]
            ]
        }
    ];

    /* ── Lo guardado en esta pestaña (sessionStorage) ── */
    function leerGuardado() {
        try { return JSON.parse(sessionStorage.getItem(CLAVE)) || { asis: {}, notas: {} }; }
        catch (e) { return { asis: {}, notas: {} }; }
    }
    function escribirGuardado(g) {
        try { sessionStorage.setItem(CLAVE, JSON.stringify(g)); } catch (e) { /* sin almacenamiento: solo en memoria */ }
    }
    var guardado = leerGuardado();

    /* ── Construcción de la base (lo que "ya estaba" en la BD) ── */
    var base = { registros: {}, notas: {} };

    function clasesDe(g) {
        var dias = {}, out = [];
        g.horario.forEach(function (s) { dias[s.diaIso] = s; });
        for (var d = parse(g.inicio); ymd(d) < g.fin; d = masDias(d, 1)) {
            var s = dias[diaIso(d)];
            if (s) out.push({ fecha: ymd(d), dia: s.dia, ini: s.ini, fin: s.fin });
        }
        return out;
    }
    // Ya dictada: antes de hoy, o hoy si la sesión ya terminó (AsistenciaService::fechasDictadas)
    function dictada(c) { return c.fecha < hoyStr || (c.fecha === hoyStr && minAhora >= minutos(c.fin)); }

    // CalificacionService::fechasModulos: las clases se reparten en orden según las horas de cada módulo
    function fechasModulos(g, clases) {
        var total = g.modulosBase.reduce(function (s, m) { return s + m[1]; }, 0), n = clases.length, acum = 0;
        if (!n || !total) return [];
        return g.modulosBase.map(function (m, i) {
            var desde = Math.min(Math.floor(n * acum / total), n - 1);
            acum += m[1];
            var hasta = Math.min(Math.max(desde, Math.floor(n * acum / total) - 1), n - 1);
            var ini = clases[desde].fecha, fin = clases[hasta].fecha;
            return {
                id: g.id * 10 + i + 1, nombre: m[0], horas: m[1], ini: ini, fin: fin,
                estado: fin < hoyStr ? 'terminado' : (ini <= hoyStr ? 'en_curso' : 'por_iniciar')
            };
        });
    }

    var grupos = BASE.map(function (b, gi) {
        var r = azar('grupo-' + b.id);
        var desde = masDias(masMeses(hoy, -b.mesesAtras), b.ajusteDias);
        var horario = b.horario.map(function (h) { return { dia: h[0], diaIso: DIAS[h[0]], ini: h[1], fin: h[2] }; })
            .sort(function (x, y) { return x.diaIso - y.diaIso; });
        var dias = horario.map(function (h) { return h.diaIso; });
        while (dias.indexOf(diaIso(desde)) === -1) desde = masDias(desde, 1);
        var inicio = ymd(desde);
        var g = {
            id: b.id, codigo: b.codigo, nombre: b.nombre, programa: b.programa, escuela: b.escuela, jornada: b.jornada,
            estado: b.estado, soloConsulta: b.estado === 'finalizado', cupo: b.cupo, duracion: b.duracion,
            inicio: inicio, fin: ymd(masMeses(parse(inicio), b.duracion)), horario: horario, modulosBase: b.modulos
        };
        var clases = clasesDe(g);
        var hechas = clases.filter(dictada);

        g.estudiantes = b.estudiantes.map(function (e, i) {
            var extra = e[4] || {};
            var nac = ymd(masDias(masMeses(hoy, -12 * e[2] - Math.floor(r() * 11)), -Math.floor(r() * 25)));
            var menor = e[2] < 18;
            var matricula = { codigo: 'EST-' + inicio.slice(0, 4) + '-' + pad(gi + 1) + pad(i + 11), fecha: ymd(masDias(parse(inicio), -Math.floor(r() * 20) - 3)), estado: 'activo', estadoFecha: null };
            if (extra.tarde) matricula.fecha = ymd(masDias(parse(inicio), extra.tarde));
            if (extra.aplazado) { matricula.estado = 'aplazado'; matricula.estadoFecha = ymd(masDias(hoy, -extra.aplazado)); }
            if (extra.retirado) { matricula.estado = 'retirado'; matricula.estadoFecha = ymd(masDias(parse(inicio), extra.retirado)); }
            if (b.estado === 'finalizado' && matricula.estado === 'activo') { matricula.estado = 'graduado'; matricula.estadoFecha = g.fin; }
            return {
                id: b.id * 100 + i + 1, nombres: e[0], apellidos: e[1], tipoDoc: menor ? 'TI' : 'CC',
                documento: String(menor ? 1000000000 + Math.floor(r() * 99999999) : 10000000 + Math.floor(r() * 1089999999)),
                fechaNac: nac, edad: edadDe(nac), menor: menor, telefono: '3' + Math.floor(100000000 + r() * 899999999),
                acudiente: e[3] ? { nombre: e[3][0], parentesco: e[3][1], telefono: '3' + Math.floor(100000000 + r() * 899999999) } : null,
                matricula: matricula,
                desde: matricula.fecha > inicio ? matricula.fecha : inicio,
                hasta: (matricula.estado === 'aplazado' || matricula.estado === 'retirado') ? matricula.estadoFecha : null,
                _faltas: !!extra.faltas, _nivel: extra.nivelBajo ? 2.7 : 3.4 + r() * 1.2
            };
        });

        // Asistencia ya registrada: las clases dictadas menos las "sin registrar" de ejemplo y la de hoy
        var saltar = {};
        saltar[hoyStr] = true;
        b.sinRegistrar.forEach(function (k) { var c = hechas.filter(function (x) { return x.fecha < hoyStr; }).slice(-1 - k)[0]; if (c) saltar[c.fecha] = true; });
        base.registros[g.id] = {};
        hechas.forEach(function (c) {
            if (saltar[c.fecha]) return;
            var dia = {};
            g.estudiantes.forEach(function (e) {
                if (c.fecha < e.desde || (e.hasta && c.fecha >= e.hasta)) return;
                var x = r(), ausencia = e._faltas ? 0.27 : 0.06;
                var estado = x < 0.03 ? 'excusa' : (x < 0.03 + ausencia ? 'ausente' : (x < 0.09 + ausencia ? 'tarde' : 'presente'));
                dia[e.id] = { estado: estado, motivo: estado === 'excusa' ? MOTIVOS[Math.floor(r() * MOTIVOS.length)] : null };
            });
            base.registros[g.id][c.fecha] = dia;
        });

        // Notas ya puestas en los módulos terminados
        var modulos = fechasModulos(g, clases);
        var terminados = modulos.filter(function (m) { return m.estado === 'terminado'; });
        base.notas[g.id] = {};
        terminados.forEach(function (m, mi) {
            var ultimo = mi === terminados.length - 1;
            if (ultimo && b.moduloSinNotas) return;
            var notas = {};
            g.estudiantes.forEach(function (e, ei) {
                if (m.fin < e.desde || (e.hasta && e.hasta <= m.fin)) return; // no lo cursó, o se fue antes de que terminara
                if (ultimo && b.faltaUnaNota && ei === 3) return;
                var n = Math.round(Math.min(5, Math.max(1, e._nivel + (r() - 0.5) * 1.4)) * 10) / 10;
                var h = null;
                if (n < APRUEBA && !ultimo) h = Math.round((3.0 + r() * 1.3) * 10) / 10; // en los módulos viejos ya habilitó
                notas[e.id] = { n: n, h: h };
            });
            base.notas[g.id][m.id] = notas;
        });
        g._clases = clases;
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
    // La que cuenta: si aprobó con la nota, esa; si no, la de la habilitación cuando la presentó
    function notaFinal(n, h) { return n >= APRUEBA || h === null || h === undefined ? n : h; }

    function registrosDe(gid, fecha) {
        var g = guardado.asis[gid] && guardado.asis[gid][fecha];
        return g || (base.registros[gid] && base.registros[gid][fecha]) || null;
    }
    function notasDe(gid, mid) {
        var b = (base.notas[gid] && base.notas[gid][mid]) || {}, g = (guardado.notas[gid] && guardado.notas[gid][mid]) || {};
        var out = {};
        Object.keys(b).forEach(function (k) { out[k] = b[k]; });
        Object.keys(g).forEach(function (k) { out[k] = g[k]; });
        return out;
    }
    function enVentana(e, fecha) { return fecha >= e.desde && !(e.hasta && fecha >= e.hasta); }

    /* ── Lo que se deriva (se recalcula después de cada guardado) ── */
    function calcular() {
        grupos.forEach(function (g) {
            var hechas = g._clases.filter(dictada);
            g.modulos = fechasModulos(g, g._clases);
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
                    var estado;
                    if (m.fin < e.desde) estado = 'no_cursado';
                    else if (x && x.n !== null && x.n !== undefined) estado = estadoNota(x.n, x.h);
                    else estado = m.estado === 'por_iniciar' ? 'por_iniciar' : 'sin_nota';
                    e.notas[m.id] = { n: x ? x.n : null, h: x ? x.h : null, estado: estado };
                    if (x && x.n !== null && x.n !== undefined) finales.push(notaFinal(x.n, x.h));
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

            // Módulos ya terminados (o en curso) en los que a algún estudiante activo le falta la nota
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
    function buscarGrupo(id) { return grupos.filter(function (g) { return g.id === +id; })[0] || null; }
    function fallar(msg) { return new Promise(function (_, rej) { setTimeout(function () { rej(new Error(msg)); }, 250); }); }
    function ok(msg) { return new Promise(function (res) { setTimeout(function () { res({ ok: true, mensaje: msg }); }, 350); }); }
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    function fechaCorta(f) { var d = parse(f); return d.getDate() + ' ' + MESES[d.getMonth()]; }
    function decimalValido(v) { return typeof v === 'number' && isFinite(v) && v >= 1 && v <= 5 && Math.round(v * 10) === v * 10; }

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
            var g = buscarGrupo(grupoId);
            if (!g) return fallar('Ese grupo no es tuyo.');
            if (g.soloConsulta) return fallar('El grupo ya terminó: su asistencia solo se consulta.');
            var clase = g.clases.filter(function (c) { return c.fecha === fecha; })[0];
            if (!clase) return fallar('Ese día no hay clase en el horario del grupo.');
            if (clase.estado === 'futura') return fallar('Esa clase todavía no ha llegado.');
            var lista = planilla(g, fecha), limpio = {};
            for (var i = 0; i < lista.length; i++) {
                var e = lista[i], x = registros[e.id];
                if (!x || ESTADOS_ASISTENCIA.indexOf(x.estado) === -1) return fallar('Falta marcar a ' + e.nombres + ' ' + e.apellidos + '.');
                var motivo = x.estado === 'excusa' ? String(x.motivo || '').trim() : null;
                if (x.estado === 'excusa' && motivo.length < 3) return fallar('Escribe el motivo de la excusa de ' + e.nombres + '.');
                limpio[e.id] = { estado: x.estado, motivo: motivo };
            }
            guardado.asis[g.id] = guardado.asis[g.id] || {};
            guardado.asis[g.id][fecha] = limpio;
            escribirGuardado(guardado);
            calcular();
            return ok('Guardaste la asistencia del ' + clase.dia.toLowerCase() + ' ' + fechaCorta(fecha) + '.');
        },

        /* notas: { estudianteId: {n: número|null, h: número|null} } — de 1.0 a 5.0, un decimal; habilitación solo si n < 3.0 */
        guardarNotas: function (grupoId, moduloId, notas) {
            var g = buscarGrupo(grupoId);
            if (!g) return fallar('Ese grupo no es tuyo.');
            if (g.soloConsulta) return fallar('El grupo ya terminó: sus notas solo se consultan.');
            var m = g.modulos.filter(function (x) { return x.id === +moduloId; })[0];
            if (!m) return fallar('Ese módulo no es de este grupo.');
            if (m.estado === 'por_iniciar') return fallar('Ese módulo todavía no empieza.');
            var limpio = {}, ids = Object.keys(notas);
            for (var i = 0; i < ids.length; i++) {
                var e = g.estudiantes.filter(function (x) { return x.id === +ids[i]; })[0];
                if (!e) return fallar('Ese estudiante no es de este grupo.');
                var n = notas[ids[i]].n, h = notas[ids[i]].h;
                n = n === '' || n === undefined ? null : n; h = h === '' || h === undefined ? null : h;
                if (e.matricula.estado !== 'activo') return fallar('La matrícula de ' + e.nombres + ' no está activa: sus notas solo se consultan.');
                if (e.notas[m.id].estado === 'no_cursado') return fallar(e.nombres + ' no cursó este módulo en el grupo.');
                if (n !== null && !decimalValido(n)) return fallar('La nota de ' + e.nombres + ' debe ir de 1.0 a 5.0, con un decimal.');
                if (h !== null && m.estado !== 'terminado') return fallar('La habilitación se presenta cuando el módulo termina.');
                if (h !== null && (n === null || n >= APRUEBA)) return fallar(e.nombres + ' no perdió el módulo: no lleva habilitación.');
                if (h !== null && !decimalValido(h)) return fallar('La habilitación de ' + e.nombres + ' debe ir de 1.0 a 5.0, con un decimal.');
                limpio[e.id] = { n: n, h: h };
            }
            guardado.notas[g.id] = guardado.notas[g.id] || {};
            guardado.notas[g.id][m.id] = guardado.notas[g.id][m.id] || {};
            Object.keys(limpio).forEach(function (k) { guardado.notas[g.id][m.id][k] = limpio[k]; });
            escribirGuardado(guardado);
            calcular();
            return ok('Guardaste las notas de ' + m.nombre + '.');
        },

        reiniciar: function () {
            try { sessionStorage.removeItem(CLAVE); } catch (e) { /* nada que borrar */ }
            guardado = { asis: {}, notas: {} };
            calcular();
        }
    };
})();
