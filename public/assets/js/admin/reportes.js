/* ESAT — Admin: Reportes. Lee REP_DATOS (ReporteService: los datos reales de la sede, con sus fechas).
   Reportes POR MES: arriba se elige el mes y cada documento sale como estaba al cierre de ese mes (el mes en curso,
   hasta hoy). Nada se guarda: cualquier mes se vuelve a armar con las fechas reales (cuándo se matriculó o se retiró
   cada uno, cuándo se puso cada nota, qué clases hubo y qué se había pagado de cada cobro a esa fecha).
   Cada documento es un modelo { titulo, sub, para, resumen, grafica, tablas, notas, firmas } que se pinta igual en la
   vista previa, en la impresión y en el Excel. Las reglas son las del sistema: asistencia = (P + T) / (P + T + A), la
   excusa no cuenta como falla; nota de 1.0 a 5.0, aprueba 3.0, una habilitación; vencido = cobro con saldo y fecha
   de vencimiento anterior al corte. */
(function () {
    'use strict';

    var CFG = window.REP_CFG;
    var D = window.REP_DATOS;
    var HOY = CFG.hoy;
    var MES_KEY = 'esat.admin.reportes.mes';
    var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    var MESES_L = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    /* ── Utilidades ───────────────────────────── */
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function miles(v) { return String(Math.round(v || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
    function pesos(v) { return '$ ' + miles(v); }
    function dos(n) { return (n < 10 ? '0' : '') + n; }
    function fecha(f) { if (!f) return '—'; var p = f.slice(0, 10).split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
    function larga(f) { var p = f.slice(0, 10).split('-'); return +p[2] + ' de ' + MESES_L[+p[1] - 1] + ' de ' + p[0]; }
    function corta(f) { var p = f.slice(0, 10).split('-'); return +p[2] + ' ' + MESES[+p[1] - 1] + (p[0] !== HOY.slice(0, 4) ? ' ' + p[0] : ''); }
    function mesNombre(ym) { return MESES_L[+ym.slice(5, 7) - 1] + ' ' + ym.slice(0, 4); }
    function mayus(t) { return t.charAt(0).toUpperCase() + t.slice(1); }
    function finDeMes(ym) { var d = new Date(+ym.slice(0, 4), +ym.slice(5, 7), 0).getDate(); return ym + '-' + dos(d); }
    function dias(a, b) { return Math.round((Date.parse(b + 'T12:00:00') - Date.parse(a + 'T12:00:00')) / 86400000); }
    function edad(nac, ref) {
        if (!nac) return null;
        var r = ref.split('-'), n = nac.split('-'), e = +r[0] - +n[0];
        if (+r[1] < +n[1] || (+r[1] === +n[1] && +r[2] < +n[2])) e--;
        return e;
    }
    function pct(a, b) { return b ? Math.round(a * 100 / b) : 0; }
    function nombre(e) { return e.apellidos + ' ' + e.nombres; }
    function genero(g) { return g === 'femenino' ? 'Femenino' : (g === 'masculino' ? 'Masculino' : 'Sin dato'); }
    function plural(n, uno, varios) { return n + ' ' + (n === 1 ? uno : varios); }
    var ESTADO_MAT = { activo: 'Activo', aplazado: 'Aplazado', retirado: 'Retirado', graduado: 'Graduado' };

    var toastEl = document.getElementById('rep-toast');
    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.hidden = false;
        requestAnimationFrame(function () { toastEl.classList.add('is-visible'); });
        clearTimeout(toast.t);
        toast.t = setTimeout(function () {
            toastEl.classList.remove('is-visible');
            setTimeout(function () { toastEl.hidden = true; }, 220);
        }, 5000);
    }

    /* ── Índices de los datos ───────────────────────────── */
    var estPorId = {}, grupoPorId = {}, matPorId = {};
    D.estudiantes.forEach(function (e) { estPorId[e.id] = e; });
    D.grupos.forEach(function (g) { grupoPorId[g.id] = g; });
    D.matriculas.forEach(function (m) { matPorId[m.id] = m; });
    var programas = [];
    D.grupos.forEach(function (g) { if (!programas.some(function (p) { return p.id === g.programa_id; })) programas.push({ id: g.programa_id, nombre: g.programa }); });
    programas.sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); });
    var progPorId = {};
    programas.forEach(function (p) { progPorId[p.id] = p.nombre; });
    function grupoTxt(m) { var g = grupoPorId[m.grupo_id]; return (progPorId[m.programa_id] || '') + (g ? ' · ' + g.nombre : ''); }
    function porNombre(a, b) { return nombre(estPorId[a.estudiante_id]).localeCompare(nombre(estPorId[b.estudiante_id]), 'es'); }

    /* ═══════════ Cómo estaba todo a una fecha (el corte del mes) ═══════════ */
    /* Estado de una matrícula a la fecha: el último cambio de estado solo cuenta desde su fecha */
    function estadoAl(m, d) {
        if (m.fecha > d) return null;
        return m.estado_fecha && m.estado_fecha <= d ? m.estado : 'activo';
    }
    function vigenteAl(m, d) { var e = estadoAl(m, d); return e === 'activo' || e === 'aplazado'; }
    /* Asistencia de un estudiante hasta la fecha (clases dictadas hasta ese día) */
    function asistenciaAl(m, d) {
        var a = D.asistencia.find(function (x) { return x.matricula_id === m.id; });
        if (!a) return null;
        var g = grupoPorId[a.grupo_id], c = { P: 0, T: 0, A: 0, E: 0 };
        g.fechas.forEach(function (f, i) { if (f <= d) { var x = a.cod.charAt(i); if (c[x] !== undefined) c[x]++; } });
        var tom = c.P + c.T + c.A;
        return { c: c, pct: tom ? pct(c.P + c.T, tom) : null };
    }
    /* Nota de un módulo a la fecha (solo lo que ya se había puesto) y su estado */
    function notaAl(matId, modId, d) {
        var k = D.notas.find(function (n) { return n.matricula_id === matId && n.modulo_id === modId; });
        if (!k || k.fecha > d) return null;
        var h = k.hab !== null && k.fecha_hab && k.fecha_hab <= d ? k.hab : null;
        var estado = k.nota >= 3 ? 'aprobado' : (h === null ? 'por_habilitar' : (h >= 3 ? 'habilito' : 'reprobado'));
        return { n: k.nota, h: h, estado: estado };
    }
    function estadoModulo(mod, d) { return !mod.ini ? 'sin_fechas' : (mod.fin < d ? 'terminado' : (mod.ini <= d ? 'en_curso' : 'por_iniciar')); }
    /* Saldo de un cobro a la fecha: el valor menos lo pagado hasta ese día */
    function saldoAl(c, d) { return c.valor - c.pagos.reduce(function (s, p) { return s + (p[0] <= d ? p[1] : 0); }, 0); }
    /* Mora por matrícula a la fecha: lo vencido y el cobro más viejo */
    function moraAl(d, f) {
        var out = {};
        D.cobros.forEach(function (c) {
            var m = matPorId[c.matricula_id];
            if (!m || m.fecha > d || !(c.vence < d) || (f && f.programa && String(m.programa_id) !== f.programa)) return;
            var s = saldoAl(c, d);
            if (s <= 0) return;
            var x = out[m.id] = out[m.id] || { m: m, b: [0, 0, 0, 0], valor: 0, dias: 0 };
            var dd = dias(c.vence, d);
            x.b[dd <= 30 ? 0 : (dd <= 60 ? 1 : (dd <= 90 ? 2 : 3))] += s;
            x.valor += s; x.dias = Math.max(x.dias, dd);
        });
        return out;
    }

    /* ── Meses disponibles: desde la primera matrícula hasta el mes en curso ── */
    var MES_HOY = HOY.slice(0, 7);
    var MESES_OPC = [];
    (function () {
        var primero = D.matriculas.reduce(function (m, x) { return x.fecha && x.fecha < m ? x.fecha : m; }, HOY).slice(0, 7);
        for (var ym = MES_HOY; ym >= primero;) {
            MESES_OPC.push(ym);
            var y = +ym.slice(0, 4), mm = +ym.slice(5, 7) - 1;
            if (mm === 0) { mm = 12; y--; }
            ym = y + '-' + dos(mm);
        }
    }());
    var mes = MES_HOY;
    function corte() { return mes === MES_HOY ? HOY : finDeMes(mes); }
    function desde() { return mes + '-01'; }
    function corteTxt() { return mes === MES_HOY ? 'Mes en curso: datos hasta hoy, ' + larga(HOY) + '.' : 'Como estaba al cierre del mes: ' + larga(corte()) + '.'; }
    function mesTxt() { return mayus(mesNombre(mes)) + (mes === MES_HOY ? ' (hasta hoy)' : ''); }

    /* ── Catálogo ── */
    var ICON = {
        secretaria: '<svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M9 21v-6h6v6"/></svg>',
        aula: '<svg viewBox="0 0 24 24"><path d="M2 4h20v12H2z"/><path d="M8 20h8M12 16v4"/></svg>',
        admin: '<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'
    };
    var GRUPOS_DOC = [
        { id: 'secretaria', titulo: 'Para la Secretaría de Educación', sub: 'Matrícula y certificación que reporta la institución.' },
        { id: 'aula', titulo: 'Para los docentes y el aula', sub: 'Listas y planillas de un grupo, para imprimir y firmar.' },
        { id: 'admin', titulo: 'Para la administración', sub: 'Lo que cruza varias pantallas y ninguna muestra por sí sola.' }
    ];
    var DOCS = {
        matriculados: { grupo: 'secretaria', titulo: 'Matriculados del mes', desc: 'Estudiantes con matrícula en el mes: documento, género, edad, residencia, programa, jornada y estado.', filtros: ['programa'] },
        certificados: { grupo: 'secretaria', titulo: 'Certificados del mes', desc: 'Quién se graduó en el mes, de qué programa y con qué nota promedio.', filtros: ['programa'] },
        retirados: { grupo: 'secretaria', titulo: 'Retiros y aplazamientos del mes', desc: 'Quién se retiró o aplazó en el mes, por qué, cuánto estudió y cuántos módulos aprobó.', filtros: ['programa'] },
        lista: { grupo: 'aula', titulo: 'Lista de clase', desc: 'Los estudiantes que tenía el grupo en el mes, con espacios para firmar la asistencia.', filtros: ['grupo'] },
        planilla: { grupo: 'aula', titulo: 'Planilla de notas del grupo', desc: 'Las notas de cada módulo como estaban al cierre del mes, con su situación, para firmar.', filtros: ['grupo'] },
        asistencia: { grupo: 'aula', titulo: 'Asistencia del mes', desc: 'Cada clase del mes con P, T, A o E por estudiante, sus totales y el porcentaje.', filtros: ['grupo'] },
        cartera: { grupo: 'admin', titulo: 'Cartera por edades', desc: 'Lo que se debía al cierre del mes y desde hace cuánto: 1–30, 31–60, 61–90 y más de 90 días.', filtros: ['programa'] },
        riesgo: { grupo: 'admin', titulo: 'Estudiantes en riesgo', desc: 'Quién tenía al cierre del mes baja asistencia, notas perdidas o mora de más de 30 días.', filtros: ['programa'] },
        caracterizacion: { grupo: 'admin', titulo: 'Caracterización de los estudiantes', desc: 'Los estudiantes del mes por género, edad, municipio de residencia y programa.', filtros: ['programa'] }
    };
    function pintarCatalogo() {
        document.getElementById('rep-catalogo').innerHTML = GRUPOS_DOC.map(function (g) {
            var docs = Object.keys(DOCS).filter(function (k) { return DOCS[k].grupo === g.id; });
            return '<div class="rep-grupo"><div class="rep-grupo-titulo"><h3>' + g.titulo + '</h3><span>' + g.sub + '</span></div><div class="rep-tarjetas">' +
                docs.map(function (k) {
                    var d = DOCS[k];
                    return '<button type="button" class="rep-tarjeta" data-doc="' + k + '"><span class="rep-tarjeta-cab"><span class="rep-tarjeta-icon g-' + g.id + '">' + ICON[g.id] + '</span><strong>' + d.titulo + '</strong></span>' +
                        '<p>' + d.desc + '</p><span class="rep-tarjeta-pie">Por mes y por ' + (d.filtros[0] === 'grupo' ? 'grupo' : 'programa') + '</span></button>';
                }).join('') + '</div></div>';
        }).join('');
    }

    /* ═══════════ Generadores: cada uno devuelve el modelo del documento ═══════════ */
    function base(tipo, sub) {
        return { tipo: tipo, titulo: DOCS[tipo].titulo, para: GRUPOS_DOC.find(function (g) { return g.id === DOCS[tipo].grupo; }).titulo,
            sub: sub, generado: CFG.ahora, por: CFG.admin, resumen: [], grafica: null, tablas: [], notas: [], firmas: [['Elaboró', CFG.admin + ' · Administración de la sede']] };
    }
    function enPrograma(m, f) { return !f.programa || String(m.programa_id) === f.programa; }
    function subPrograma(f) { return mesTxt() + ' · ' + (f.programa ? 'Programa: ' + progPorId[f.programa] : 'Todos los programas'); }
    function subGrupo(g) { return mesTxt() + ' · ' + g.programa + ' · ' + g.nombre + ' · Jornada ' + g.jornada.toLowerCase(); }
    function barrasPor(lista, clave, orden) {
        var c = {};
        lista.forEach(function (x) { var k = clave(x); c[k] = (c[k] || 0) + 1; });
        var out = Object.keys(c).map(function (k) { return { lbl: k, val: c[k], txt: c[k] }; });
        return orden ? out.sort(orden) : out.sort(function (a, b) { return b.val - a.val || a.lbl.localeCompare(b.lbl, 'es'); });
    }
    function aprobadosAl(m, d) {
        var g = grupoPorId[m.grupo_id]; if (!g) return null;
        var n = g.modulos.filter(function (mod) { var k = notaAl(m.id, mod.id, d); return k && (k.estado === 'aprobado' || k.estado === 'habilito'); }).length;
        return n + ' de ' + g.modulos.length;
    }

    var GEN = {
        matriculados: function (f) {
            var d = corte(), ini = desde();
            // Con matrícula en algún momento del mes: matriculado antes del corte y sin terminar (retiro o grado) antes del mes
            var lista = D.matriculas.filter(function (m) {
                return enPrograma(m, f) && m.fecha <= d && !((m.estado === 'retirado' || m.estado === 'graduado') && m.estado_fecha && m.estado_fecha < ini);
            }).sort(porNombre);
            var doc = base('matriculados', subPrograma(f));
            var mujeres = lista.filter(function (m) { return estPorId[m.estudiante_id].genero === 'femenino'; }).length;
            var menores = lista.filter(function (m) { var e = edad(estPorId[m.estudiante_id].nacimiento, d); return e !== null && e < 18; }).length;
            doc.resumen = [['Matriculados', lista.length], ['Nuevos en el mes', lista.filter(function (m) { return m.fecha >= ini; }).length], ['Mujeres', mujeres], ['Hombres', lista.length - mujeres], ['Menores de edad', menores]];
            doc.grafica = lista.length ? { titulo: 'Matriculados por programa', barras: barrasPor(lista, function (m) { return progPorId[m.programa_id]; }) } : null;
            var estadoTxt = function (m) { var st = estadoAl(m, d); return ESTADO_MAT[st] + (st !== 'activo' && m.estado_fecha ? ' (' + fecha(m.estado_fecha) + ')' : ''); };
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Documento' }, { t: 'Apellidos y nombres' }, { t: 'Género' }, { t: 'Nacimiento · edad' },
                { t: 'Municipio' }, { t: 'Programa, grupo y jornada' }, { t: 'Matrícula' }, { t: 'Estado al cierre' }],
                filas: lista.map(function (m, i) {
                    var e = estPorId[m.estudiante_id], g = grupoPorId[m.grupo_id];
                    return [i + 1, e.tipo_doc + ' ' + e.documento, nombre(e), genero(e.genero), fecha(e.nacimiento) + ' · ' + edad(e.nacimiento, d) + ' años', e.municipio || '—',
                        progPorId[m.programa_id] + (g ? ' · ' + g.nombre + ' · ' + g.jornada : ''), fecha(m.fecha), estadoTxt(m)];
                }), vacio: 'No había matrículas en este mes.',
                // En el Excel van todas las columnas por separado (es lo que se carga en los formatos de la Secretaría)
                excel: { cols: [{ t: 'N°' }, { t: 'Tipo doc.' }, { t: 'Documento' }, { t: 'Apellidos' }, { t: 'Nombres' }, { t: 'Género' }, { t: 'Nacimiento' }, { t: 'Edad' },
                    { t: 'Municipio' }, { t: 'Programa' }, { t: 'Jornada' }, { t: 'Grupo' }, { t: 'Matrícula' }, { t: 'Estado al cierre' }],
                    filas: lista.map(function (m, i) {
                        var e = estPorId[m.estudiante_id], g = grupoPorId[m.grupo_id];
                        return [i + 1, e.tipo_doc, e.documento, e.apellidos, e.nombres, genero(e.genero), fecha(e.nacimiento), edad(e.nacimiento, d), e.municipio || '',
                            progPorId[m.programa_id], g ? g.jornada : '', g ? g.nombre : '', fecha(m.fecha), estadoTxt(m)];
                    }) } });
            doc.notas.push('Se cuentan las matrículas que estuvieron vigentes en algún momento del mes. La edad es la que tenían al cierre del mes.');
            return doc;
        },
        certificados: function (f) {
            var d = corte(), ini = desde();
            var lista = D.matriculas.filter(function (m) { return m.estado === 'graduado' && enPrograma(m, f) && m.estado_fecha >= ini && m.estado_fecha <= d; })
                .sort(function (a, b) { return a.estado_fecha.localeCompare(b.estado_fecha); });
            var doc = base('certificados', subPrograma(f));
            doc.resumen = [['Certificados', lista.length], ['Programas', barrasPor(lista, function (m) { return m.programa_id; }).length]];
            doc.grafica = lista.length ? { titulo: 'Certificados por programa', barras: barrasPor(lista, function (m) { return progPorId[m.programa_id]; }) } : null;
            var promedio = function (m) {
                var ns = D.notas.filter(function (n) { return n.matricula_id === m.id; }).map(function (n) { return n.nota >= 3 || n.hab === null ? n.nota : n.hab; });
                return ns.length ? (ns.reduce(function (s, x) { return s + x; }, 0) / ns.length).toFixed(1) : '—';
            };
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Documento' }, { t: 'Apellidos y nombres' }, { t: 'Programa y grupo' }, { t: 'Matrícula' }, { t: 'Fecha de grado' }, { t: 'Nota promedio', num: 1 }],
                filas: lista.map(function (m, i) {
                    var e = estPorId[m.estudiante_id];
                    return [i + 1, e.tipo_doc + ' ' + e.documento, nombre(e), grupoTxt(m), fecha(m.fecha), fecha(m.estado_fecha), promedio(m)];
                }), vacio: 'Nadie se graduó en este mes.',
                excel: { cols: [{ t: 'N°' }, { t: 'Tipo doc.' }, { t: 'Documento' }, { t: 'Apellidos' }, { t: 'Nombres' }, { t: 'Programa' }, { t: 'Grupo' }, { t: 'Matrícula' }, { t: 'Fecha de grado' }, { t: 'Nota promedio' }],
                    filas: lista.map(function (m, i) {
                        var e = estPorId[m.estudiante_id], g = grupoPorId[m.grupo_id];
                        return [i + 1, e.tipo_doc, e.documento, e.apellidos, e.nombres, progPorId[m.programa_id], g ? g.nombre : '', fecha(m.fecha), fecha(m.estado_fecha), promedio(m)];
                    }) } });
            doc.firmas.push(['Rectoría', 'Escuela Nacional de Educación ESAT']);
            return doc;
        },
        retirados: function (f) {
            var d = corte(), ini = desde();
            var lista = D.matriculas.filter(function (m) { return (m.estado === 'retirado' || m.estado === 'aplazado') && enPrograma(m, f) && m.estado_fecha >= ini && m.estado_fecha <= d; })
                .sort(function (a, b) { return a.estado_fecha.localeCompare(b.estado_fecha); });
            var doc = base('retirados', subPrograma(f));
            var ret = lista.filter(function (m) { return m.estado === 'retirado'; }).length;
            doc.resumen = [['Retiros', ret], ['Aplazamientos', lista.length - ret], ['Total', lista.length]];
            doc.grafica = lista.length ? { titulo: 'Retiros y aplazamientos por programa', barras: barrasPor(lista, function (m) { return progPorId[m.programa_id]; }) } : null;
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Documento' }, { t: 'Apellidos y nombres' }, { t: 'Programa y grupo' }, { t: 'Situación' }, { t: 'Fecha' }, { t: 'Motivo' }, { t: 'Estudió', num: 1 }, { t: 'Módulos aprobados', num: 1 }],
                filas: lista.map(function (m, i) {
                    var e = estPorId[m.estudiante_id], meses = Math.max(0, Math.round(dias(m.fecha, m.estado_fecha) / 30.4));
                    return [i + 1, e.documento, nombre(e), grupoTxt(m), ESTADO_MAT[m.estado], fecha(m.estado_fecha), m.motivo || '—', plural(meses, 'mes', 'meses'), aprobadosAl(m, m.estado_fecha) || '—'];
                }), vacio: 'No hubo retiros ni aplazamientos en este mes.' });
            return doc;
        },
        lista: function (f) {
            var g = grupoPorId[f.grupo], d = corte();
            var lista = D.matriculas.filter(function (m) { return m.grupo_id === g.id && vigenteAl(m, d); }).sort(porNombre);
            var doc = base('lista', subGrupo(g));
            doc.resumen = [['Estudiantes', lista.filter(function (m) { return estadoAl(m, d) === 'activo'; }).length], ['Aplazados', lista.filter(function (m) { return estadoAl(m, d) === 'aplazado'; }).length],
                ['Docente', g.docente || 'Sin asignar'], ['Horario', g.horario || '—']];
            var clases = g.fechas.filter(function (x) { return x.slice(0, 7) === mes; });
            var cols = (clases.length ? clases.slice(0, 8) : ['', '', '', '', '']).map(function (x) {
                if (!x) return { t: 'Fecha: ____', firma: 1 };
                var dd = new Date(x + 'T12:00:00'); return { t: DIAS[dd.getDay()] + ' ' + dd.getDate(), firma: 1 };
            });
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Documento' }, { t: 'Apellidos y nombres' }, { t: 'Celular' }].concat(cols),
                filas: lista.map(function (m, i) {
                    var e = estPorId[m.estudiante_id];
                    return [i + 1, e.documento, nombre(e) + (estadoAl(m, d) === 'aplazado' ? ' (aplazado)' : ''), e.celular || '—'].concat(cols.map(function () { return ''; }));
                }), vacio: 'El grupo no tenía estudiantes en este mes.' });
            if (clases.length > 8) doc.notas.push('El mes tuvo ' + clases.length + ' clases; la lista trae espacio para las primeras 8.');
            doc.firmas = [['Docente', g.docente || '________________'], ['Coordinación académica', 'Sede ' + D.sede.nombre]];
            return doc;
        },
        planilla: function (f) {
            var g = grupoPorId[f.grupo], d = corte(), mods = g.modulos;
            var lista = D.matriculas.filter(function (m) { return m.grupo_id === g.id && m.fecha <= d && (vigenteAl(m, d) || (m.estado_fecha && m.estado_fecha >= desde())); }).sort(porNombre);
            var doc = base('planilla', subGrupo(g) + ' · Docente: ' + (g.docente || 'sin asignar'));
            var SIT = { aprobado: 'A', habilito: 'H', por_habilitar: 'PH', reprobado: 'R' };
            var estMod = mods.map(function (m) { return estadoModulo(m, d); });
            var filas = lista.map(function (m) { return { m: m, ns: mods.map(function (mod) { return notaAl(m.id, mod.id, d); }) }; });
            var porMod = mods.map(function (mod, j) {
                var con = filas.map(function (x) { return x.ns[j]; }).filter(Boolean);
                var aprob = con.filter(function (k) { return k.estado === 'aprobado' || k.estado === 'habilito'; }).length;
                return { con: con.length, aprob: aprob, prom: con.length ? con.reduce(function (s, k) { return s + (k.h !== null && k.n < 3 ? k.h : k.n); }, 0) / con.length : null };
            });
            var perdidos = filas.reduce(function (s, x) { return s + x.ns.filter(function (k) { return k && (k.estado === 'por_habilitar' || k.estado === 'reprobado'); }).length; }, 0);
            doc.resumen = [['Estudiantes', lista.length], ['Módulos', mods.length], ['Terminados al cierre', estMod.filter(function (s) { return s === 'terminado'; }).length], ['Módulos perdidos', perdidos]];
            var conNota = porMod.map(function (x, j) { return { x: x, j: j }; }).filter(function (y) { return y.x.con; });
            doc.grafica = conNota.length ? { titulo: 'Aprobación por módulo (estudiantes con nota)', max: 100, barras: conNota.map(function (y) {
                return { lbl: 'M' + (y.j + 1) + ' · ' + mods[y.j].nombre, val: pct(y.x.aprob, y.x.con), txt: pct(y.x.aprob, y.x.con) + ' % · ' + y.x.aprob + ' de ' + y.x.con };
            }) } : null;
            doc.tablas.push({ cuadricula: 1, cols: [{ t: 'N°', num: 1 }, { t: 'Apellidos y nombres' }].concat(mods.map(function (m, j) { return { t: 'M' + (j + 1), cen: 1 }; })).concat([{ t: 'Promedio', num: 1 }, { t: 'Aprobados', num: 1 }]),
                filas: filas.map(function (x, i) {
                    var e = estPorId[x.m.estudiante_id];
                    var vals = x.ns.filter(Boolean).map(function (k) { return k.h !== null && k.n < 3 ? k.h : k.n; });
                    return [i + 1, nombre(e)].concat(mods.map(function (mod, j) {
                        var k = x.ns[j];
                        if (!k) {
                            var t = estMod[j] === 'en_curso' ? 'curso' : (estMod[j] === 'terminado' ? (mod.fin < x.m.fecha ? 'n/c' : 'sin nota') : '·');
                            return { t: t, clase: 'is-suave' };
                        }
                        return { t: k.n.toFixed(1) + (k.h !== null ? ' / ' + k.h.toFixed(1) : '') + ' ' + SIT[k.estado], clase: k.estado === 'reprobado' || k.estado === 'por_habilitar' ? 'is-mal' : '' };
                    })).concat([vals.length ? (vals.reduce(function (s, v) { return s + v; }, 0) / vals.length).toFixed(1) : '—',
                        x.ns.filter(function (k) { return k && (k.estado === 'aprobado' || k.estado === 'habilito'); }).length + ' de ' + mods.length]);
                }),
                pie: ['', 'Promedio del módulo'].concat(porMod.map(function (x) { return x.prom === null ? '—' : x.prom.toFixed(1); })).concat(['', '']),
                vacio: 'El grupo no tenía estudiantes en este mes.' });
            doc.tablas.push({ titulo: 'Módulos del programa', cols: [{ t: '' }, { t: 'Módulo' }, { t: 'Horas', num: 1 }, { t: 'Fechas' }, { t: 'Al cierre del mes' }],
                filas: mods.map(function (m, j) {
                    return ['M' + (j + 1), m.nombre, m.horas, m.ini ? corta(m.ini) + ' – ' + corta(m.fin) : '—', { terminado: 'Terminado', en_curso: 'En curso', por_iniciar: 'Por iniciar', sin_fechas: 'Sin horario' }[estMod[j]]];
                }) });
            doc.notas.push('Escala de 1.0 a 5.0; se aprueba con 3.0. "2.5 / 3.4" = nota y habilitación. A aprobado · H aprobó con habilitación · PH por habilitar · R reprobado · n/c el módulo terminó antes de su matrícula. Solo se cuentan las notas puestas hasta el cierre del mes.');
            doc.firmas = [['Docente', g.docente || '________________'], ['Coordinación académica', 'Sede ' + D.sede.nombre]];
            return doc;
        },
        asistencia: function (f) {
            var g = grupoPorId[f.grupo], d = corte();
            var idx = [];
            g.fechas.forEach(function (x, i) { if (x.slice(0, 7) === mes && x <= d) idx.push(i); });
            var lista = D.asistencia.filter(function (a) { return a.grupo_id === g.id; }).sort(porNombre);
            var doc = base('asistencia', subGrupo(g));
            var filas = lista.map(function (a, i) {
                var c = { P: 0, T: 0, A: 0, E: 0 };
                var celdas = idx.map(function (k) { var x = a.cod.charAt(k); if (c[x] !== undefined) c[x]++; return { t: c[x] !== undefined ? x : '·', clase: x === 'A' ? 'is-mal' : (x === 'P' ? '' : 'is-suave') }; });
                var tom = c.P + c.T + c.A;
                return { a: a, p: tom ? pct(c.P + c.T, tom) : null, c: c, celdas: celdas };
            }).filter(function (x) { return x.p !== null || x.c.E > 0; });
            filas.forEach(function (x, i) { x.fila = [i + 1, nombre(estPorId[x.a.estudiante_id])].concat(x.celdas).concat([x.c.P, x.c.T, x.c.A, x.c.E, x.p !== null ? x.p + ' %' : '—']); });
            var conPct = filas.filter(function (x) { return x.p !== null; });
            doc.resumen = [['Clases en el mes', idx.length], ['Estudiantes', filas.length], ['Asistencia del grupo', conPct.length ? Math.round(conPct.reduce(function (s, x) { return s + x.p; }, 0) / conPct.length) + ' %' : '—'],
                ['Bajo el 80 %', conPct.filter(function (x) { return x.p < 80; }).length]];
            doc.grafica = conPct.length ? { titulo: 'Asistencia del mes por estudiante', max: 100, barras: conPct.map(function (x) { return { lbl: nombre(estPorId[x.a.estudiante_id]), val: x.p, txt: x.p + ' %' }; }) } : null;
            doc.tablas.push({ cuadricula: 1, cols: [{ t: 'N°', num: 1 }, { t: 'Estudiante' }].concat(idx.map(function (k) {
                var dd = new Date(g.fechas[k] + 'T12:00:00'); return { t: DIAS[dd.getDay()] + ' ' + dd.getDate(), cen: 1 };
            })).concat([{ t: 'P', num: 1 }, { t: 'T', num: 1 }, { t: 'A', num: 1 }, { t: 'E', num: 1 }, { t: '%', num: 1 }]),
                filas: filas.map(function (x) { return x.fila; }), vacio: idx.length ? 'Nadie del grupo tiene asistencia registrada en este mes.' : 'El grupo no tuvo clases en este mes.' });
            var excusas = [];
            lista.forEach(function (a) { idx.forEach(function (k) { if (a.motivos && a.motivos[k]) excusas.push([nombre(estPorId[a.estudiante_id]), fecha(g.fechas[k]), a.motivos[k]]); }); });
            if (excusas.length) doc.tablas.push({ titulo: 'Excusas del mes', cols: [{ t: 'Estudiante' }, { t: 'Fecha' }, { t: 'Motivo' }], filas: excusas });
            doc.notas.push('P presente · T tarde · A ausente · E excusa · "·" clase sin registrar. Porcentaje = (P + T) / (P + T + A): la excusa no cuenta como falla.');
            doc.firmas = [['Docente', g.docente || '________________']];
            return doc;
        },
        cartera: function (f) {
            var d = corte(), mora = moraAl(d, f);
            var lista = Object.keys(mora).map(function (k) { return mora[k]; }).sort(function (a, b) { return b.dias - a.dias || b.valor - a.valor; });
            var tot = [0, 0, 0, 0];
            lista.forEach(function (x) { for (var i = 0; i < 4; i++) tot[i] += x.b[i]; });
            var total = tot[0] + tot[1] + tot[2] + tot[3];
            var doc = base('cartera', 'Fecha de corte: ' + larga(d) + ' · ' + (f.programa ? 'Programa: ' + progPorId[f.programa] : 'Todos los programas'));
            doc.resumen = [['Total vencido', pesos(total)], ['Estudiantes en mora', lista.length], ['Más de 60 días', pesos(tot[2] + tot[3])], ['Del total, más de 60 días', pct(tot[2] + tot[3], total) + ' %']];
            var EDADES = ['1 a 30 días', '31 a 60 días', '61 a 90 días', 'Más de 90 días'];
            doc.grafica = total ? { titulo: 'Vencido según su edad', barras: EDADES.map(function (e, i) { return { lbl: e, val: tot[i], txt: pesos(tot[i]) }; }) } : null;
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Estudiante' }, { t: 'Programa y grupo' }, { t: 'Celular' }, { t: '1–30', num: 1 }, { t: '31–60', num: 1 }, { t: '61–90', num: 1 }, { t: '+90', num: 1 }, { t: 'Total', num: 1 }, { t: 'Días', num: 1 }],
                filas: lista.map(function (x, i) {
                    var e = estPorId[x.m.estudiante_id], cel = e.acudiente && edad(e.nacimiento, d) < 18 ? e.acudiente.celular + ' (acudiente)' : (e.celular || '—');
                    return [i + 1, nombre(e), grupoTxt(x.m), cel].concat(x.b.map(function (v) { return v ? pesos(v) : '—'; })).concat([pesos(x.valor), { t: x.dias, clase: x.dias > 60 ? 'is-mal' : '' }]);
                }), pie: ['', 'Total', '', ''].concat(tot.map(pesos)).concat([pesos(total), '']), vacio: 'No había cobros vencidos a esta fecha.' });
            doc.notas.push('Se cuenta lo que faltaba por pagar de cada cobro con los pagos recibidos hasta la fecha de corte; la edad va desde su vencimiento (el día 10). A un menor de edad se le cobra al acudiente.');
            return doc;
        },
        riesgo: function (f) {
            var d = corte(), mora = moraAl(d);
            var lista = D.matriculas.filter(function (m) { return vigenteAl(m, d) && enPrograma(m, f); }).map(function (m) {
                var a = asistenciaAl(m, d), g = grupoPorId[m.grupo_id];
                var ns = g ? g.modulos.map(function (mod) { return notaAl(m.id, mod.id, d); }).filter(Boolean) : [];
                var ph = ns.filter(function (k) { return k.estado === 'por_habilitar'; }).length, rep = ns.filter(function (k) { return k.estado === 'reprobado'; }).length;
                var mo = mora[m.id];
                var al = { asis: !!(a && a.pct !== null && a.pct < 80), notas: ph + rep > 0, mora: !!(mo && mo.dias > 30) };
                return { m: m, a: a, ph: ph, rep: rep, mo: mo, al: al, cuantas: (al.asis ? 1 : 0) + (al.notas ? 1 : 0) + (al.mora ? 1 : 0) };
            }).filter(function (x) { return x.cuantas > 0; }).sort(function (a, b) { return b.cuantas - a.cuantas || porNombre(a.m, b.m); });
            var doc = base('riesgo', 'Al ' + larga(d) + ' · ' + (f.programa ? 'Programa: ' + progPorId[f.programa] : 'Todos los programas'));
            var cuenta = function (k) { return lista.filter(function (x) { return x.al[k]; }).length; };
            doc.resumen = [['En riesgo', lista.length], ['Asistencia bajo 80 %', cuenta('asis')], ['Notas perdidas', cuenta('notas')], ['Mora de más de 30 días', cuenta('mora')]];
            doc.grafica = lista.length ? { titulo: 'Estudiantes según cuántas alertas tenían', barras: [3, 2, 1].map(function (k) {
                var n = lista.filter(function (x) { return x.cuantas === k; }).length; return { lbl: plural(k, 'alerta', 'alertas'), val: n, txt: plural(n, 'estudiante', 'estudiantes') };
            }) } : null;
            doc.tablas.push({ cols: [{ t: 'N°', num: 1 }, { t: 'Estudiante' }, { t: 'Programa y grupo' }, { t: 'Asistencia', num: 1 }, { t: 'Notas' }, { t: 'Mora' }, { t: 'Alertas', num: 1 }, { t: 'Contacto' }],
                filas: lista.map(function (x, i) {
                    var e = estPorId[x.m.estudiante_id], menor = edad(e.nacimiento, d) < 18;
                    var notasTxt = [x.ph ? plural(x.ph, 'por habilitar', 'por habilitar') : '', x.rep ? plural(x.rep, 'reprobado', 'reprobados') : ''].filter(Boolean).join(' · ') || 'Al día';
                    return [i + 1, nombre(e), grupoTxt(x.m),
                        { t: x.a && x.a.pct !== null ? x.a.pct + ' %' : '—', clase: x.al.asis ? 'is-mal' : '' },
                        { t: notasTxt, clase: x.al.notas ? 'is-mal' : '' },
                        { t: x.mo ? pesos(x.mo.valor) + ' · ' + x.mo.dias + ' días' : 'Al día', clase: x.al.mora ? 'is-mal' : '' },
                        x.cuantas, menor && e.acudiente ? e.acudiente.nombre + ' · ' + e.acudiente.celular : (e.celular || '—')];
                }), vacio: 'Ningún estudiante tenía alertas a esta fecha.' });
            doc.notas.push('Alertas al cierre del mes: asistencia acumulada bajo el 80 % (la misma regla de Asistencia), al menos un módulo por habilitar o reprobado, y un cobro vencido hace más de 30 días. De un menor de edad se da el contacto del acudiente.');
            return doc;
        },
        caracterizacion: function (f) {
            var d = corte();
            var est = D.matriculas.filter(function (m) { return vigenteAl(m, d) && enPrograma(m, f); }).map(function (m) { return { m: m, e: estPorId[m.estudiante_id] }; });
            var total = est.length;
            var doc = base('caracterizacion', 'Estudiantes con matrícula vigente al ' + larga(d) + ' · ' + (f.programa ? 'Programa: ' + progPorId[f.programa] : 'Todos los programas'));
            var RANGOS = [['Menores de 18', 0, 17], ['18 a 24 años', 18, 24], ['25 a 34 años', 25, 34], ['35 años o más', 35, 200]];
            var porRango = RANGOS.map(function (r) { return est.filter(function (x) { var a = edad(x.e.nacimiento, d); return a !== null && a >= r[1] && a <= r[2]; }).length; });
            var mujeres = est.filter(function (x) { return x.e.genero === 'femenino'; }).length;
            var edades = est.map(function (x) { return edad(x.e.nacimiento, d); }).filter(function (a) { return a !== null; });
            doc.resumen = [['Estudiantes', total], ['Mujeres', mujeres + ' · ' + pct(mujeres, total) + ' %'], ['Hombres', (total - mujeres) + ' · ' + pct(total - mujeres, total) + ' %'],
                ['Edad promedio', edades.length ? Math.round(edades.reduce(function (s, a) { return s + a; }, 0) / edades.length) + ' años' : '—']];
            doc.grafica = total ? { titulo: 'Estudiantes por rango de edad', barras: RANGOS.map(function (r, i) { return { lbl: r[0], val: porRango[i], txt: porRango[i] + ' · ' + pct(porRango[i], total) + ' %' }; }) } : null;
            var tabla = function (titulo, col, filas) { doc.tablas.push({ titulo: titulo, cols: [{ t: col }, { t: 'Estudiantes', num: 1 }, { t: '%', num: 1 }],
                filas: filas.map(function (x) { return [x.lbl, x.val, pct(x.val, total) + ' %']; }), pie: ['Total', total, total ? '100 %' : '—'], vacio: 'No había estudiantes en este mes.' }); };
            tabla('Por género', 'Género', barrasPor(est, function (x) { return genero(x.e.genero); }));
            tabla('Por municipio de residencia', 'Municipio', barrasPor(est, function (x) { return x.e.municipio || 'Sin dato'; }));
            doc.tablas.push({ titulo: 'Por programa', cols: [{ t: 'Programa' }, { t: 'Estudiantes', num: 1 }, { t: 'Mujeres', num: 1 }, { t: 'Hombres', num: 1 }, { t: 'Menores', num: 1 }],
                filas: barrasPor(est, function (x) { return progPorId[x.m.programa_id]; }).map(function (b) {
                    var de = est.filter(function (x) { return progPorId[x.m.programa_id] === b.lbl; });
                    var muj = de.filter(function (x) { return x.e.genero === 'femenino'; }).length;
                    return [b.lbl, b.val, muj, b.val - muj, de.filter(function (x) { return edad(x.e.nacimiento, d) < 18; }).length];
                }), pie: ['Total', total, mujeres, total - mujeres, porRango[0]], vacio: 'No había estudiantes en este mes.' });
            return doc;
        }
    };

    /* ═══════════ Pintar el documento ═══════════ */
    function celda(c) { return c && typeof c === 'object' ? c : { t: c, clase: '' }; }
    function graficaHtml(g) {
        var max = g.max || Math.max.apply(null, g.barras.map(function (b) { return b.val; }).concat([1]));
        return '<div class="rep-graf"><p class="rep-graf-titulo">' + esc(g.titulo) + '</p><ul class="rep-graf-filas">' + g.barras.map(function (b) {
            var w = max ? Math.max(b.val ? 1 : 0, Math.round(b.val * 100 / max)) : 0;
            return '<li class="rep-graf-fila" title="' + esc(b.lbl + ': ' + b.txt) + '"><span class="rep-graf-lbl">' + esc(b.lbl) + '</span>' +
                '<span class="rep-graf-pista"><span class="rep-graf-barra" style="width:calc(' + w + '% * 0.82)"></span><span class="rep-graf-val">' + esc(b.txt) + '</span></span></li>';
        }).join('') + '</ul></div>';
    }
    function tablaHtml(t) {
        var cls = function (c) { return c.num ? 'num' : (c.cen ? 'cen' : (c.firma ? 'firma' : '')); };
        var html = (t.titulo ? '<h2 class="rep-hoja-sub">' + esc(t.titulo) + '</h2>' : '');
        if (!t.filas.length) return html + '<p class="rep-hoja-vacio">' + esc(t.vacio || 'Sin datos.') + '</p>';
        return html + '<div class="rep-hoja-tabla-wrap"><table class="rep-hoja-tabla' + (t.cuadricula ? ' is-cuadricula' : '') + '"><thead><tr>' +
            t.cols.map(function (c) { return '<th class="' + cls(c) + '">' + esc(c.t) + '</th>'; }).join('') + '</tr></thead><tbody>' +
            t.filas.map(function (f) {
                return '<tr>' + f.map(function (v, i) { var c = celda(v); return '<td class="' + cls(t.cols[i] || {}) + (c.clase ? ' ' + c.clase : '') + '">' + esc(c.t) + '</td>'; }).join('') + '</tr>';
            }).join('') + '</tbody>' +
            (t.pie ? '<tfoot><tr>' + t.pie.map(function (v, i) { return '<td class="' + cls(t.cols[i] || {}) + '">' + esc(v) + '</td>'; }).join('') + '</tr></tfoot>' : '') + '</table></div>';
    }
    function hojaHtml(doc) {
        return '<header class="rep-hoja-cab"><div class="rep-hoja-marca"><img src="' + esc(CFG.logo) + '" alt="ESAT"><div>' +
                '<strong>Escuela Nacional de Educación ESAT</strong><span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>' +
                '<span>Sede ' + esc(D.sede.nombre) + (D.sede.ciudad ? ' · ' + esc(D.sede.ciudad) : '') + '</span></div></div>' +
            '<div class="rep-hoja-num"><span>' + esc(doc.para) + '</span><strong>' + esc((D.sede.ciudad ? D.sede.ciudad + ', ' : '') + larga(doc.generado)) + '</strong></div></header>' +
            '<h1 class="rep-hoja-titulo">' + esc(doc.titulo) + '</h1><p class="rep-hoja-filtros">' + esc(doc.sub) + '</p>' +
            (doc.resumen.length ? '<div class="rep-hoja-resumen">' + doc.resumen.map(function (r) { return '<div><span>' + esc(r[0]) + '</span><strong>' + esc(r[1]) + '</strong></div>'; }).join('') + '</div>' : '') +
            (doc.grafica ? graficaHtml(doc.grafica) : '') +
            doc.tablas.map(tablaHtml).join('') +
            doc.notas.map(function (n) { return '<p class="rep-hoja-nota">' + esc(n) + '</p>'; }).join('') +
            '<div class="rep-hoja-firmas">' + doc.firmas.map(function (f) { return '<div class="rep-hoja-firma">' + esc(f[0]) + '<span>' + esc(f[1]) + '</span></div>'; }).join('') + '</div>' +
            '<p class="rep-hoja-pie">Generado el ' + esc(corta(doc.generado)) + ' a las ' + esc(doc.generado.slice(11, 16)) + ' por ' + esc(doc.por) + ' desde la plataforma de ESAT.</p>';
    }

    /* Imprimir: una copia de la hoja como hijo directo de <body>; lo demás se oculta */
    var impresion = document.getElementById('rep-impresion');
    document.body.appendChild(impresion);
    function imprimir(doc) {
        impresion.innerHTML = '<article class="rep-hoja">' + hojaHtml(doc) + '</article>';
        var img = impresion.querySelector('img');
        var listo = function () { window.print(); };
        if (img && !img.complete) { img.onload = listo; img.onerror = listo; } else listo();
    }

    /* Excel: el título, los filtros, el resumen y cada tabla (CSV con ; y BOM para las tildes) */
    function celdaCsv(v) {
        var t = v == null ? '' : String(v);
        // Una celda que empieza con = + - @ se toma como fórmula en Excel: se neutraliza
        if (/^[=+\-@\t\r]/.test(t)) t = "'" + t;
        return /[";\n\r]/.test(t) ? '"' + t.replace(/"/g, '""') + '"' : t;
    }
    function excel(doc) {
        var filas = [[doc.titulo], [doc.sub], ['Generado el ' + fecha(doc.generado) + ' ' + doc.generado.slice(11, 16) + ' por ' + doc.por], []];
        if (doc.resumen.length) { doc.resumen.forEach(function (r) { filas.push([r[0], r[1]]); }); filas.push([]); }
        doc.tablas.forEach(function (t0) {
            var t = t0.excel ? { titulo: t0.titulo, cols: t0.excel.cols, filas: t0.excel.filas, pie: null } : t0;
            if (t.titulo) filas.push([t.titulo]);
            filas.push(t.cols.map(function (c) { return c.t; }));
            t.filas.forEach(function (f) { filas.push(f.map(function (v) { return celda(v).t; })); });
            if (t.pie) filas.push(t.pie);
            filas.push([]);
        });
        doc.notas.forEach(function (n) { filas.push([n]); });
        var csv = '﻿' + filas.map(function (r) { return r.map(celdaCsv).join(';'); }).join('\r\n');
        var nombreArchivo = (doc.titulo + ' ' + mes).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.csv';
        var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        var a = document.createElement('a');
        a.href = url; a.download = nombreArchivo;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        toast('Se descargó ' + nombreArchivo + '.');
    }

    /* ── Combobox (mismo patrón que las demás pantallas) ── */
    function initCombobox(root) {
        var trigger = root.querySelector('[data-combobox-trigger]');
        var label   = root.querySelector('[data-combobox-label]');
        var panel   = root.querySelector('[data-combobox-panel]');
        var list    = root.querySelector('[data-combobox-list]');
        var hidden  = root.querySelector('[data-combobox-value]');
        var placeholder = label.textContent;
        function abrir() { panel.hidden = false; root.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true'); }
        function cerrar() { if (panel.hidden) return; panel.hidden = true; root.classList.remove('is-open'); trigger.setAttribute('aria-expanded', 'false'); }
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(); });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) cerrar(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });
        root.setOptions = function (opciones) {
            root.opciones = opciones;
            list.innerHTML = '';
            opciones.forEach(function (o) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.dataset.value = o.value;
                li.innerHTML = '<span>' + esc(o.label) + '</span><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
                li.addEventListener('click', function () { root.setValue(o.value, o.label); cerrar(); });
                list.appendChild(li);
            });
        };
        root.setValue = function (valor, etiqueta, opts) {
            hidden.value = valor || '';
            label.textContent = etiqueta || placeholder;
            list.querySelectorAll('li[data-value]').forEach(function (li) { li.classList.toggle('is-selected', li.dataset.value === String(valor)); });
            if (!(opts && opts.silent)) hidden.dispatchEvent(new Event('change', { bubbles: true }));
        };
        root.value = function () { return hidden.value; };
        root.etiqueta = function (v) { var o = (root.opciones || []).find(function (x) { return x.value === v; }); return o ? o.label : ''; };
    }
    var CB = { mes: document.getElementById('rep-cb-mes'), programa: document.getElementById('rep-cb-programa'), grupo: document.getElementById('rep-cb-grupo') };
    Object.keys(CB).forEach(function (k) { initCombobox(CB[k]); });
    function poner(k, v) { CB[k].setValue(v, CB[k].etiqueta(v), { silent: true }); }
    CB.mes.setOptions(MESES_OPC.map(function (ym) { return { value: ym, label: mayus(mesNombre(ym)) + (ym === MES_HOY ? ' (en curso)' : '') }; }));
    CB.programa.setOptions([{ value: '', label: 'Todos los programas' }].concat(programas.map(function (p) { return { value: String(p.id), label: p.nombre }; })));
    poner('programa', '');

    /* Grupos que sirven para cada documento en el mes elegido */
    function gruposPara(tipo) {
        var d = corte();
        return D.grupos.filter(function (g) {
            if (tipo === 'asistencia') return g.fechas.some(function (x) { return x.slice(0, 7) === mes && x <= d; });
            var tiene = D.matriculas.some(function (m) { return m.grupo_id === g.id && vigenteAl(m, d); });
            if (tipo === 'planilla') return tiene && g.modulos.some(function (m) { return m.ini && m.ini <= d; });
            return tiene;
        });
    }
    function opcionesGrupo() {
        var gs = gruposPara(actual);
        CB.grupo.setOptions(gs.map(function (g) { return { value: String(g.id), label: g.programa + ' · ' + g.nombre + ' (' + g.jornada + ')' }; }));
        if (!gs.some(function (g) { return String(g.id) === CB.grupo.value(); })) poner('grupo', gs.length ? String(gs[0].id) : '');
        return gs;
    }

    /* ═══════════ Mes y visor ═══════════ */
    var catalogo = document.getElementById('rep-catalogo');
    var visor = document.getElementById('rep-visor');
    var hoja = document.getElementById('rep-hoja');
    var actual = null, docActual = null;
    var AYUDA = { lista: 'Los grupos con estudiantes en el mes.', planilla: 'Los grupos con módulos iniciados en el mes.', asistencia: 'Los grupos que tuvieron clases en el mes.' };

    function elegirMes(ym) {
        mes = ym;
        poner('mes', ym);
        document.getElementById('rep-mes-corte').textContent = corteTxt();
        try { localStorage.setItem(MES_KEY, ym); } catch (e) {}
        if (actual) { if (DOCS[actual].filtros[0] === 'grupo') opcionesGrupo(); generar(); }
    }
    CB.mes.querySelector('[data-combobox-value]').addEventListener('change', function () { elegirMes(CB.mes.value()); });
    CB.programa.querySelector('[data-combobox-value]').addEventListener('change', generar);
    CB.grupo.querySelector('[data-combobox-value]').addEventListener('change', generar);

    function abrirDoc(tipo) {
        actual = tipo;
        var f = DOCS[tipo].filtros[0];
        CB.programa.hidden = f !== 'programa';
        CB.grupo.hidden = f !== 'grupo';
        if (f === 'grupo') opcionesGrupo();
        document.getElementById('rep-ayuda-filtro').textContent = AYUDA[tipo] || '';
        catalogo.hidden = true;
        visor.hidden = false;
        generar();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function filtros() {
        var f = {};
        DOCS[actual].filtros.forEach(function (k) { f[k] = CB[k].value(); });
        return f;
    }
    /* El papel mide 1000 px; si el marco es más angosto, se reduce entero (sin scroll horizontal) */
    var marco = document.querySelector('.rep-hoja-marco');
    var PAPEL = 1000;
    function escalar() {
        if (visor.hidden) return;
        hoja.style.transform = ''; hoja.style.marginLeft = ''; marco.style.height = '';
        var cs = getComputedStyle(marco), pad = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
        var s = Math.min(1, (marco.clientWidth - pad) / PAPEL);
        if (s >= 1) return;
        hoja.style.transform = 'scale(' + s + ')';
        hoja.style.marginLeft = '0';
        marco.style.height = Math.ceil(hoja.offsetHeight * s + parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom)) + 'px';
    }
    window.addEventListener('resize', function () { clearTimeout(escalar.t); escalar.t = setTimeout(escalar, 80); });

    function generar() {
        if (!actual) return;
        var f = filtros();
        if (f.grupo !== undefined && !f.grupo) {
            docActual = null;
            hoja.innerHTML = '<p class="rep-hoja-vacio">' + ({ asistencia: 'Ningún grupo tuvo clases en este mes.', planilla: 'Ningún grupo tenía módulos iniciados en este mes.' }[actual] || 'Ningún grupo tenía estudiantes en este mes.') + '</p>';
            escalar();
            return;
        }
        docActual = GEN[actual](f);
        hoja.innerHTML = hojaHtml(docActual);
        escalar();
    }
    catalogo.addEventListener('click', function (e) { var b = e.target.closest('[data-doc]'); if (b) abrirDoc(b.dataset.doc); });
    document.getElementById('rep-volver').addEventListener('click', function () {
        actual = null; visor.hidden = true; catalogo.hidden = false;
        var b = catalogo.querySelector('.rep-tarjeta'); if (b) b.focus({ preventScroll: true });
    });
    document.getElementById('rep-imprimir').addEventListener('click', function () { if (docActual) imprimir(docActual); else toast('No hay documento para imprimir en este mes.'); });
    document.getElementById('rep-excel').addEventListener('click', function () { if (docActual) excel(docActual); else toast('No hay documento para descargar en este mes.'); });

    /* Inicializar: el último mes elegido (o el mes en curso) */
    pintarCatalogo();
    var guardado = null;
    try { guardado = localStorage.getItem(MES_KEY); } catch (e) {}
    elegirMes(guardado && MESES_OPC.indexOf(guardado) !== -1 ? guardado : MES_HOY);
}());
