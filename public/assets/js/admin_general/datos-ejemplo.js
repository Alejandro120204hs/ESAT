/* =========================================================
   ESAT — Panel Admin (ESAT general): DATOS DE EJEMPLO
   Simula lo que mandará el servidor mientras no hay backend. Lo usan todas las pantallas del panel,
   así que cuadran entre sí (los recibos suman los ingresos del Inicio, los estudiantes en mora suman la
   cartera, etc.).
   - REALES: nombres, direcciones y ubicación de las 4 sedes (sitio público), programas (BD), NIT,
     licencia, teléfonos de contacto y la cuota del sistema ($ 2.000, configuraciones.cuota_sistema_mensual).
   - FICTICIOS: personas (secretarías, estudiantes, acudientes, docentes), montos, recibos, egresos,
     comunicados y auditoría. Se generan con una semilla fija, y las fechas se arman alrededor de hoy.
   - GEN_API guarda lo que se crea o edita en sessionStorage (por pestaña) para que las pantallas se vean
     coherentes. Al conectar el backend: cada controlador pasa sus datos con @json en esta misma forma y
     GEN_API se cambia por fetch() a rutas reales con Form Requests.
   ========================================================= */
(function () {
    var DIA = 86400000, ahora = Date.now(), HOY = new Date(ahora);
    var YO = window.GEN_YO || 'Admin';

    /* ── Azar con semilla fija (siempre los mismos datos) ── */
    var semilla = 20261109;
    function rnd() { semilla = (semilla * 1103515245 + 12345) % 2147483648; return semilla / 2147483648; }
    function de(arr) { return arr[Math.floor(rnd() * arr.length)]; }
    function entre(a, b) { return a + Math.floor(rnd() * (b - a + 1)); }
    function iso(d) { return d.toISOString(); }
    function haceDias(d, horas) { return iso(new Date(ahora - d * DIA - (horas || 0) * 3600000)); }
    function dosDig(n) { return (n < 10 ? '0' : '') + n; }
    function fechaCorta(d) { return d.getFullYear() + '-' + dosDig(d.getMonth() + 1) + '-' + dosDig(d.getDate()); }
    /* Primer día del mes que está `atras` meses antes del actual */
    function mesAtras(atras) { return new Date(HOY.getFullYear(), HOY.getMonth() - atras, 1); }

    /* ── Catálogo (REAL, de la BD): escuelas, programas con sus módulos, precios y jornadas. Los 3 programas de prueba
       (Inglés B1, Desarrollo web, Técnico en matemáticas) no se incluyen. El precio es igual en todas las sedes. ── */
    var escuelas = [
        { id: 1, nombre: 'Salud', sigla: 'SAL' }, { id: 2, nombre: 'Cocina y Turismo', sigla: 'TUR' }, { id: 3, nombre: 'Administrativa', sigla: 'ADM' },
        { id: 4, nombre: 'Educación e Idiomas', sigla: 'EDU' }, { id: 5, nombre: 'Ciencias', sigla: 'CIE' }
    ];
    var programas = [
        { id: 1, codigo: 'ESA-SAL-001', nombre: 'Técnico Laboral en Servicios Geriátricos', escuela_id: 1, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1800, resolucion: 'Res. 4521-2022 · SED Bogotá', jornadas: ['Mañana','Tarde'], cupo: 25, precio_total: 2030000, matricula: 350000, duracion_meses: 12, tipo_periodo: 'semestre', duracion_periodos: 2, estado: 'activo',
          modulos: [{nombre:'Fundamentos de gerontología',horas:160},{nombre:'Cuidado básico de enfermería',horas:240},{nombre:'Nutrición en el adulto mayor',horas:120},{nombre:'Actividades terapéuticas',horas:160},{nombre:'Ética y legislación en salud',horas:80},{nombre:'Primeros auxilios',horas:80},{nombre:'Práctica clínica',horas:960}] },
        { id: 2, codigo: 'ESA-SAL-002', nombre: 'Técnico Laboral en Auxiliar de Psiquiatría', escuela_id: 1, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1800, resolucion: 'Res. 3876-2021 · SED Bogotá', jornadas: ['Mañana','Noche'], cupo: 20, precio_total: 2120000, matricula: 380000, duracion_meses: 12, tipo_periodo: 'semestre', duracion_periodos: 2, estado: 'activo',
          modulos: [{nombre:'Introducción a la salud mental',horas:160},{nombre:'Psicopatología básica',horas:200},{nombre:'Cuidado al paciente psiquiátrico',horas:240},{nombre:'Farmacología en psiquiatría',horas:80},{nombre:'Ética y derechos del paciente',horas:80},{nombre:'Práctica clínica',horas:1040}] },
        { id: 3, codigo: 'ESA-SAL-003', nombre: 'Técnico Laboral en Seguridad Ocupacional y Laboral', escuela_id: 1, nivel: 'Técnico Laboral', modalidad: 'Mixta', horas: 1800, resolucion: 'Res. 5104-2023 · SED Bogotá', jornadas: ['Noche','Fines de semana'], cupo: 25, precio_total: 2110000, matricula: 370000, duracion_meses: 12, tipo_periodo: 'semestre', duracion_periodos: 2, estado: 'activo',
          modulos: [{nombre:'Fundamentos de SST',horas:160},{nombre:'Identificación y control de riesgos',horas:200},{nombre:'Normativa colombiana SST',horas:120},{nombre:'Ergonomía y bienestar laboral',horas:120},{nombre:'Primeros auxilios y emergencias',horas:80},{nombre:'Práctica empresarial SST',horas:1120}] },
        { id: 4, codigo: 'ESA-TUR-001', nombre: 'Técnico Laboral en Cocina Nacional e Internacional', escuela_id: 2, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1600, resolucion: 'Res. 2210-2023 · SED Bogotá', jornadas: ['Mañana','Tarde','Noche'], cupo: 20, precio_total: 2020000, matricula: 420000, duracion_meses: 10, tipo_periodo: 'trimestre', duracion_periodos: 4, estado: 'activo',
          modulos: [{nombre:'Técnicas básicas de cocina',horas:200},{nombre:'Gastronomía colombiana',horas:160},{nombre:'Cocina internacional',horas:200},{nombre:'Panadería y repostería',horas:160},{nombre:'Higiene y seguridad alimentaria',horas:80},{nombre:'Práctica en cocina profesional',horas:800}] },
        { id: 5, codigo: 'ESA-TUR-002', nombre: 'Técnico Laboral en Servicios Hoteleros y Turísticos', escuela_id: 2, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1440, resolucion: 'En trámite · SED Bogotá', jornadas: ['Mañana'], cupo: 20, precio_total: 1390000, matricula: 310000, duracion_meses: 9, tipo_periodo: 'trimestre', duracion_periodos: 3, estado: 'en_aprobacion',
          modulos: [{nombre:'Fundamentos de turismo y hotelería',horas:160},{nombre:'Servicio al cliente turístico',horas:160},{nombre:'Operaciones hoteleras',horas:200},{nombre:'Inglés para turismo',horas:120},{nombre:'Geografía turística de Colombia',horas:80},{nombre:'Práctica hotelera',horas:720}] },
        { id: 6, codigo: 'ESA-ADM-001', nombre: 'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas', escuela_id: 3, nivel: 'Técnico Laboral', modalidad: 'Mixta', horas: 1600, resolucion: 'Res. 1895-2020 · SED Bogotá', jornadas: ['Tarde','Fines de semana'], cupo: 25, precio_total: 1600000, matricula: 300000, duracion_meses: 10, tipo_periodo: 'trimestre', duracion_periodos: 4, estado: 'activo',
          modulos: [{nombre:'Sistema de salud colombiano',horas:120},{nombre:'Codificación CIE-10 y CUPS',horas:200},{nombre:'Facturación y glosas',horas:160},{nombre:'Auditoría de cuentas médicas',horas:160},{nombre:'Normativa SOAT y PGSS',horas:80},{nombre:'Práctica supervisada',horas:880}] },
        { id: 7, codigo: 'ESA-ADM-002', nombre: 'Técnico Laboral en Auxiliar Contable y Administrativo', escuela_id: 3, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1440, resolucion: 'Res. 3102-2019 · SED Bogotá', jornadas: ['Mañana','Tarde','Noche'], cupo: 25, precio_total: 1288000, matricula: 280000, duracion_meses: 9, tipo_periodo: 'trimestre', duracion_periodos: 3, estado: 'activo',
          modulos: [{nombre:'Contabilidad general',horas:200},{nombre:'Ofimática avanzada',horas:120},{nombre:'Nómina y prestaciones sociales',horas:160},{nombre:'Tributaria básica',horas:120},{nombre:'Gestión documental',horas:80},{nombre:'Práctica empresarial',horas:760}] },
        { id: 8, codigo: 'ESA-EDU-001', nombre: 'Técnico Laboral en Asistente de Preescolar', escuela_id: 4, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1600, resolucion: 'Res. 2750-2022 · SED Bogotá', jornadas: ['Mañana','Tarde'], cupo: 22, precio_total: 1490000, matricula: 290000, duracion_meses: 10, tipo_periodo: 'trimestre', duracion_periodos: 4, estado: 'activo',
          modulos: [{nombre:'Desarrollo infantil y psicología educativa',horas:200},{nombre:'Pedagogía para la primera infancia',horas:200},{nombre:'Lúdica y expresión artística',horas:160},{nombre:'Nutrición y salud infantil',horas:80},{nombre:'Legislación educativa colombiana',horas:80},{nombre:'Práctica pedagógica',horas:880}] },
        { id: 9, codigo: 'ESA-CIE-001', nombre: 'Técnico Laboral en Electricista', escuela_id: 5, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1600, resolucion: 'Res. 4410-2022 · SED Bogotá', jornadas: ['Mañana','Noche'], cupo: 22, precio_total: 1710000, matricula: 310000, duracion_meses: 10, tipo_periodo: 'trimestre', duracion_periodos: 4, estado: 'activo',
          modulos: [{nombre:'Fundamentos de electricidad',horas:200},{nombre:'Instalaciones eléctricas residenciales',horas:240},{nombre:'Instalaciones industriales',horas:200},{nombre:'RETIE y normativa eléctrica',horas:80},{nombre:'Práctica en campo',horas:880}] },
        { id: 10, codigo: 'ESA-CIE-002', nombre: 'Técnico Laboral en Electromecánica', escuela_id: 5, nivel: 'Técnico Laboral', modalidad: 'Presencial', horas: 1600, resolucion: 'Res. 3320-2021 · SED Bogotá', jornadas: ['Tarde','Noche'], cupo: 20, precio_total: 1720000, matricula: 320000, duracion_meses: 10, tipo_periodo: 'trimestre', duracion_periodos: 4, estado: 'activo',
          modulos: [{nombre:'Electricidad y magnetismo aplicada',horas:160},{nombre:'Motores eléctricos y transformadores',horas:200},{nombre:'Automatización industrial básica',horas:200},{nombre:'Mantenimiento preventivo y correctivo',horas:160},{nombre:'Práctica industrial',horas:880}] }
    ];

    /* ── Sedes: nombre, ubicación y dirección reales; cifras ficticias ──
       ingresos: recaudado por mes, del más viejo al mes en curso (el último va hasta hoy).
       ingresoMesAntAlDia: lo recaudado el mes pasado hasta el mismo día. */
    var sedes = [
        { id: 1, nombre: 'Bogotá', departamento: 'Cundinamarca', ciudad: 'Bogotá', direccion: 'Cra 52 #71-18', creada: '2019-09-23',
          programas: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], estudiantes: 142, estudiantesAnt: 136, grupos: 8, gruposAnt: 7, gruposPorIniciar: 2,
          ingresos: [38400000, 41250000, 39800000, 44100000, 46350000, 15870000], ingresoMesAntAlDia: 14200000,
          mora: { valor: 9840000, estudiantes: 18 }, moraAnt: { valor: 11200000, estudiantes: 21 },
          nomina: 18500000, arriendo: 6400000 },
        { id: 2, nombre: 'Sasaima', departamento: 'Cundinamarca', ciudad: 'Sasaima', direccion: 'Urbanización San José, Cra 2 #8-84', creada: '2020-02-03',
          programas: [1, 2, 3, 4, 5, 6], estudiantes: 58, estudiantesAnt: 55, grupos: 3, gruposAnt: 3, gruposPorIniciar: 1,
          ingresos: [12100000, 13400000, 12850000, 14900000, 15600000, 5120000], ingresoMesAntAlDia: 5480000,
          mora: { valor: 2150000, estudiantes: 6 }, moraAnt: { valor: 1980000, estudiantes: 5 },
          nomina: 6200000, arriendo: 1900000 },
        { id: 3, nombre: 'Guaduas', departamento: 'Cundinamarca', ciudad: 'Guaduas', direccion: 'Cra 2 No. 1-83, Calle de la Pola', creada: '2021-01-18',
          programas: [3, 4, 5, 6, 7, 8], estudiantes: 71, estudiantesAnt: 74, grupos: 4, gruposAnt: 4, gruposPorIniciar: 0,
          ingresos: [16800000, 17350000, 15900000, 18200000, 16450000, 4380000], ingresoMesAntAlDia: 6050000,
          mora: { valor: 6420000, estudiantes: 15 }, moraAnt: { valor: 5100000, estudiantes: 12 },
          nomina: 7600000, arriendo: 2300000 },
        { id: 4, nombre: 'La Dorada', departamento: 'Caldas', ciudad: 'La Dorada', direccion: 'Calle 9 No. 7-55, Barrio Magdalena', creada: '2022-07-11',
          programas: [1, 3, 5, 7, 9], estudiantes: 39, estudiantesAnt: 42, grupos: 3, gruposAnt: 3, gruposPorIniciar: 1,
          ingresos: [8900000, 9450000, 8100000, 9800000, 7650000, 2240000], ingresoMesAntAlDia: 3100000,
          mora: { valor: 3910000, estudiantes: 11 }, moraAnt: { valor: 3200000, estudiantes: 9 },
          nomina: 4300000, arriendo: 1700000 }
    ];

    /* ── Secretarías (cuentas rol admin de cada sede) ── */
    var secretarias = [
        { id: 1, sede_id: 1, nombres: 'Paola Andrea', apellidos: 'Rincón Vargas', genero: 'femenino', fecha_nacimiento: '1990-04-17', tipo_documento: 'CC', numero_documento: '1019045873', telefono: '3104582217', email: 'paola.rincon@esat.edu.co', activo: true, ultimo_acceso: haceDias(0, 2), creada: haceDias(410) },
        { id: 2, sede_id: 2, nombres: 'Marcela', apellidos: 'Gutiérrez Ruiz', genero: 'femenino', fecha_nacimiento: '1987-11-02', tipo_documento: 'CC', numero_documento: '1070594412', telefono: '3125570934', email: 'marcela.gutierrez@esat.edu.co', activo: true, ultimo_acceso: haceDias(1, 3), creada: haceDias(620) },
        { id: 3, sede_id: 3, nombres: 'Laura', apellidos: 'Torres Medina', genero: 'femenino', fecha_nacimiento: '1994-06-25', tipo_documento: 'CC', numero_documento: '1073251648', telefono: '3208841126', email: 'laura.torres@esat.edu.co', activo: true, ultimo_acceso: haceDias(34, 5), creada: haceDias(300) },
        { id: 4, sede_id: 3, nombres: 'Jhon Mario', apellidos: 'Cárdenas Ortiz', genero: 'masculino', fecha_nacimiento: '1992-01-30', tipo_documento: 'CC', numero_documento: '1070611903', telefono: '3017790345', email: 'jhon.cardenas@esat.edu.co', activo: true, ultimo_acceso: haceDias(0, 4), creada: haceDias(95) },
        { id: 5, sede_id: 4, nombres: 'Ricardo', apellidos: 'Salamanca Peña', genero: 'masculino', fecha_nacimiento: '1985-09-12', tipo_documento: 'CC', numero_documento: '10187655', telefono: '3146620087', email: 'ricardo.salamanca@esat.edu.co', activo: false, ultimo_acceso: haceDias(52, 1), creada: haceDias(700) },
        { id: 6, sede_id: 1, nombres: 'Sandra Milena', apellidos: 'Bermúdez Rojas', genero: 'femenino', fecha_nacimiento: '1996-03-08', tipo_documento: 'CC', numero_documento: '1022437710', telefono: '3157204481', email: 'sandra.bermudez@esat.edu.co', activo: true, ultimo_acceso: null, creada: haceDias(2, 3) }
    ];
    /* Quién registró algo en una sede en una fecha (para recibos y auditoría): una secretaría activa en esa
       fecha o, si la sede no tenía, el Admin (entró a la sede) */
    function nombreCorto(s) { return s.nombres + ' ' + s.apellidos.split(' ')[0]; }
    function registra(sedeId, fecha) {
        var t = new Date(fecha).getTime();
        var ok = secretarias.filter(function (s) {
            if (s.sede_id !== sedeId || !s.ultimo_acceso || new Date(s.creada).getTime() > t) return false;
            /* Una secretaría activa que entró en los últimos 2 días sigue trabajando hasta hoy */
            var hasta = s.activo && ahora - new Date(s.ultimo_acceso).getTime() < 2 * DIA ? ahora : new Date(s.ultimo_acceso).getTime() + 3600000;
            return t <= hasta;
        });
        return ok.length ? { nombre: nombreCorto(ok[Math.floor(rnd() * ok.length)]), rol: 'secretaria' } : { nombre: YO, rol: 'admin' };
    }

    /* ── Estudiantes ── */
    var NOM_F = ['Valentina', 'Daniela', 'Laura Camila', 'Paula Andrea', 'Sara', 'María José', 'Angie Paola', 'Natalia', 'Yesica', 'Karen Lizeth', 'Juliana', 'Diana Marcela', 'Luisa Fernanda', 'Tatiana', 'Leidy Johana', 'Camila', 'Ana María', 'Sandra Milena', 'Gloria', 'Nicol'];
    var NOM_M = ['Juan David', 'Santiago', 'Camilo Andrés', 'Diego Fernando', 'Brayan Stiven', 'Jhon Alexander', 'Andrés Felipe', 'Cristian', 'Kevin', 'Luis Miguel', 'Sebastián', 'Óscar', 'Wilson', 'Jairo', 'Duván', 'Esteban', 'Mateo', 'Julián', 'Hernán', 'Fabián'];
    var APE = ['Moreno', 'Castro', 'Rojas', 'Peña', 'Suárez', 'León', 'Díaz', 'Ortiz', 'Romero', 'Gil', 'Pulido', 'Torres', 'Niño', 'Espinosa', 'Rueda', 'Hernández', 'Gómez', 'Ramírez', 'Cárdenas', 'Beltrán', 'Sánchez', 'Vargas', 'Murillo', 'Cifuentes', 'Ospina', 'Garzón', 'Quintero', 'Patiño', 'Acosta', 'Bermúdez'];
    var PARENTESCO = ['Madre', 'Padre', 'Abuela', 'Tía', 'Hermano'];
    var estudiantes = [], idEst = 1;

    function persona(sede) {
        var fem = rnd() < 0.62;
        var ape1 = de(APE), ape2 = de(APE);
        while (ape2 === ape1) ape2 = de(APE);
        var nombres = de(fem ? NOM_F : NOM_M), apellidos = ape1 + ' ' + ape2;
        var edad = rnd() < 0.15 ? entre(15, 17) : entre(18, 46);
        var nac = new Date(HOY.getFullYear() - edad, entre(0, 11), entre(1, 28));
        /* La edad real sale de la fecha de nacimiento (puede no haber cumplido años todavía) */
        if (HOY.getMonth() < nac.getMonth() || (HOY.getMonth() === nac.getMonth() && HOY.getDate() < nac.getDate())) edad--;
        var menor = edad < 18;
        var doc = menor ? String(entre(1000000000, 1099999999)) : String(entre(1000000000, 1099999999));
        var correo = (nombres.split(' ')[0] + '.' + apellidos.split(' ')[0]).toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '') + entre(1, 99) + '@gmail.com';
        return {
            id: idEst++, sede_id: sede.id, nombres: nombres, apellidos: apellidos, genero: fem ? 'femenino' : 'masculino',
            fecha_nacimiento: fechaCorta(nac), edad: edad, tipo_documento: menor ? 'TI' : 'CC', numero_documento: doc,
            telefono: '3' + entre(0, 2) + entre(0, 9) + String(entre(1000000, 9999999)), email: correo,
            ciudad: sede.ciudad,
            acudiente: menor ? { nombre: de(fem ? NOM_F : NOM_F.concat(NOM_M)) + ' ' + apellidos.split(' ')[0], parentesco: de(PARENTESCO), telefono: '31' + entre(0, 9) + String(entre(1000000, 9999999)) } : null
        };
    }
    /* ── Docentes y grupos ── */
    var DOC_F = ['Natalia', 'Adriana', 'Claudia Patricia', 'Liliana', 'Martha Lucía', 'Johana', 'Carolina', 'Viviana'];
    var DOC_M = ['Hernán', 'Fernando', 'Mauricio', 'Germán', 'Álvaro', 'Rodrigo', 'Javier', 'Édgar'];
    var VINC = ['tiempo_completo', 'medio_tiempo', 'hora_catedra', 'hora_catedra'];
    var docentes = [], grupos = [], idDoc = 1, idGrupo = 1;
    function nuevoDocente(sede, escuelaId) {
        var fem = rnd() < 0.5, a1 = de(APE), a2 = de(APE);
        while (a2 === a1) a2 = de(APE);
        var esc = escuelas.find(function (e) { return e.id === escuelaId; });
        var TITULO = { 1: ['Enfermero(a) profesional', 'Psicólogo(a)', 'Profesional en Seguridad y Salud en el Trabajo'], 2: ['Chef profesional', 'Administrador(a) hotelero(a)'],
            3: ['Contador(a) público(a)', 'Administrador(a) de empresas'], 4: ['Licenciado(a) en Educación Infantil'], 5: ['Ingeniero(a) electricista', 'Ingeniero(a) electromecánico(a)'] };
        var d = { id: idDoc++, nombres: de(fem ? DOC_F : DOC_M), apellidos: a1 + ' ' + a2, tipo_documento: 'CC', numero_documento: String(entre(10000000, 1099999999)),
            genero: fem ? 'femenino' : 'masculino', fecha_nacimiento: fechaCorta(new Date(HOY.getFullYear() - entre(26, 58), entre(0, 11), entre(1, 28))),
            profesion: de(TITULO[escuelaId] || ['Profesional']), especialidad: 'Docencia en ' + (esc ? esc.nombre.toLowerCase() : 'formación técnica'),
            departamento: sede.departamento, ciudad: sede.ciudad, direccion: 'Calle ' + entre(2, 40) + ' # ' + entre(1, 30) + '-' + entre(2, 90),
            escuela_id: escuelaId, vinculacion: de(VINC), sedes: [sede.id], estado: 'activo', telefono: '3' + entre(0, 2) + entre(0, 9) + String(entre(1000000, 9999999)) };
        d.email = (d.nombres.split(' ')[0] + '.' + a1).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') + '@esat.edu.co';
        docentes.push(d);
        return d;
    }
    function programa(id) { return programas.find(function (p) { return p.id === id; }); }
    function sumarMeses(f, m) { return new Date(f.getFullYear(), f.getMonth() + m, f.getDate()); }
    function crearGrupos(sede) {
        var ofertas = sede.programas.filter(function (id) { return programa(id).estado === 'activo'; }), letras = {};
        function grupo(estado, i) {
            var p = programa(ofertas[i % ofertas.length]);
            letras[p.id] = (letras[p.id] || 0) + 1;
            var inicio = estado === 'en_clases' ? new Date(ahora - entre(20, (p.duracion_meses - 1) * 30) * DIA)
                : estado === 'por_iniciar' ? new Date(ahora + entre(6, 30) * DIA) : sumarMeses(new Date(ahora - entre(10, 70) * DIA), -p.duracion_meses);
            var fin = sumarMeses(inicio, p.duracion_meses);
            var g = { id: idGrupo++, sede_id: sede.id, programa_id: p.id, nombre: 'Grupo ' + 'ABCDEF'.charAt(letras[p.id] - 1), jornada: de(p.jornadas),
                cupo: p.cupo, inicio: fechaCorta(inicio), fin: fechaCorta(fin), estado: estado, estudiantes: 0,
                sin_registrar: estado === 'en_clases' ? de([0, 0, 0, 1, 2, 3, 5]) : 0, sin_nota: estado === 'en_clases' ? de([0, 0, 1, 1, 2]) : 0 };
            /* Módulo que va: las horas del programa repartidas en el tiempo del grupo */
            var avance = Math.min(1, Math.max(0, (ahora - inicio) / (fin - inicio))), horas = avance * p.horas, acum = 0;
            g.modulo_actual = p.modulos.length - 1;
            for (var m = 0; m < p.modulos.length; m++) { acum += p.modulos[m].horas; if (horas < acum) { g.modulo_actual = m; break; } }
            g.avance = Math.round(avance * 100);
            grupos.push(g);
            return g;
        }
        var lista = [], i;
        for (i = 0; i < sede.grupos; i++) lista.push(grupo('en_clases', i));
        for (i = 0; i < sede.gruposPorIniciar; i++) lista.push(grupo('por_iniciar', i + sede.grupos));
        for (i = 0; i < 2; i++) lista.push(grupo('finalizado', i + 1));
        /* Un docente por grupo; algunos dictan dos grupos de la misma escuela */
        var propios = [];
        lista.forEach(function (g) {
            var esc = programa(g.programa_id).escuela_id;
            var libre = propios.find(function (d) { return d.escuela_id === esc && d.grupos < 2 && rnd() < 0.45; });
            var d = libre || nuevoDocente(sede, esc);
            if (!libre) { d.grupos = 0; propios.push(d); }
            d.grupos++;
            g.docente_id = d.id;
        });
        sede.cupos = lista.filter(function (g) { return g.estado !== 'finalizado'; }).reduce(function (t, g) { return t + g.cupo; }, 0);
        return lista;
    }
    function matricula(e, sede, estado, g) {
        var prog = programa(g.programa_id);
        e.programa_id = prog.id; e.programa = prog.nombre;
        e.grupo_id = g.id; e.grupo = g.nombre;
        e.estado = estado;
        var dias = estado === 'graduado' ? entre(200, 600) : entre(5, 330);
        e.fecha_matricula = fechaCorta(new Date(ahora - dias * DIA));
        e.plan = rnd() < 0.8 ? 'mensual' : 'periodo';
        e.asistencia = entre(estado === 'retirado' ? 45 : 68, 100);
        e.promedio = Math.round((3 + rnd() * 1.9) * 10) / 10;
        e.por_habilitar = estado === 'activo' && rnd() < 0.12 ? 1 : 0;   // módulos perdidos que todavía puede habilitar
        if (estado === 'aplazado' || estado === 'retirado' || estado === 'graduado') {
            e.estado_fecha = fechaCorta(new Date(ahora - entre(3, Math.max(4, dias - 2)) * DIA));
            e.estado_motivo = estado === 'aplazado' ? de(['Viaje por trabajo', 'Calamidad familiar', 'Incapacidad médica', 'Embarazo'])
                : estado === 'retirado' ? de(['Cambio de ciudad', 'Motivos económicos', 'Consiguió empleo de tiempo completo', 'No le gustó el programa']) : 'Terminó el programa';
        }
        e.saldo_vencido = 0; e.cuotas_vencidas = []; e.por_pagar_mes = 0;
        e.pago = 'al_dia';
    }
    /* Reparte un total en n partes (múltiplos de 1.000) */
    function repartir(total, n) {
        var w = [], s = 0, i;
        for (i = 0; i < n; i++) { w.push(0.4 + rnd()); s += w[i]; }
        var partes = [], acum = 0;
        for (i = 0; i < n - 1; i++) { var p = Math.round(total * w[i] / s / 1000) * 1000; partes.push(p); acum += p; }
        partes.push(total - acum);
        return partes;
    }

    sedes.forEach(function (s) {
        var activos = [], k, gs = crearGrupos(s);
        var enClases = gs.filter(function (g) { return g.estado === 'en_clases'; }), fin = gs.filter(function (g) { return g.estado === 'finalizado'; });
        for (k = 0; k < s.estudiantes; k++) {
            var g = enClases[k % enClases.length], e = persona(s);
            matricula(e, s, 'activo', g); g.estudiantes++; activos.push(e); estudiantes.push(e);
        }
        /* En mora: suman exactamente la cartera vencida de la sede */
        repartir(s.mora.valor, s.mora.estudiantes).forEach(function (saldo, i) {
            var e = activos[i * 3 % activos.length];
            if (e.saldo_vencido) e = activos.find(function (x) { return !x.saldo_vencido; });
            e.pago = 'vencido'; e.saldo_vencido = saldo;
            var n = saldo > 900000 ? entre(3, 4) : saldo > 450000 ? entre(2, 3) : 1;
            repartir(saldo, n).forEach(function (v, j) {
                e.cuotas_vencidas.push({ valor: v, vence: fechaCorta(new Date(HOY.getFullYear(), HOY.getMonth() - (HOY.getDate() >= 10 ? 0 : 1) - j, 10)) });
            });
            e.por_pagar_mes = entre(18, 42) * 10000;
        });
        activos.forEach(function (e) {
            if (e.pago !== 'al_dia') return;
            var r = rnd();
            if (r < 0.05) { e.pago = 'sin_plan'; e.plan = null; }
            else if (r < 0.32) { e.pago = 'pendiente'; e.por_pagar_mes = entre(18, 42) * 10000; }
        });
        var otros = [['aplazado', 0.05], ['retirado', 0.06], ['graduado', 0.1]];
        otros.forEach(function (o) {
            for (k = 0; k < Math.round(s.estudiantes * o[1]); k++) { var e = persona(s); matricula(e, s, o[0], o[0] === 'graduado' ? de(fin) : de(enClases)); estudiantes.push(e); }
        });
        for (k = 0; k < 2; k++) {
            var nuevo = persona(s);
            nuevo.estado = 'sin_matricula'; nuevo.programa = null; nuevo.grupo = null; nuevo.pago = 'sin_matricula'; nuevo.saldo_vencido = 0; nuevo.cuotas_vencidas = [];
            estudiantes.push(nuevo);
        }
        s.ocupados = s.estudiantes;
    });

    (function () {
        var d = docentes.find(function (x) { return x.sedes[0] === 2; });
        var g = grupos.find(function (x) { return x.sede_id === 3 && x.estado === 'por_iniciar'; }) || grupos.find(function (x) { return x.sede_id === 3 && x.estado === 'finalizado'; });
        if (d && g) { var viejo = docentes.find(function (x) { return x.id === g.docente_id; }); g.docente_id = d.id; d.sedes.push(3);
            if (viejo && !grupos.some(function (x) { return x.docente_id === viejo.id; })) docentes.splice(docentes.indexOf(viejo), 1); }
        var inactivo = nuevoDocente(sedes[0], 1); inactivo.estado = 'inactivo'; inactivo.grupos = 0;
    })();
    docentes.forEach(function (d) { delete d.grupos; });

    /* ── Acudientes (cuenta rol acudiente): se arman con los de los menores; algunos tienen dos estudiantes ── */
    var acudientes = [], idAcu = 1;
    estudiantes.forEach(function (e) {
        if (!e.acudiente) return;
        var hermano = rnd() < 0.15 && acudientes.find(function (a) { return a.sede_id === e.sede_id && a.apellidos.split(' ')[0] === e.apellidos.split(' ')[0]; });
        var a = hermano;
        if (!a) {
            /* El género sale del parentesco (Madre, Abuela, Tía → mujer; Padre, Hermano → hombre) */
            var fem = ['Madre', 'Abuela', 'Tía'].indexOf(e.acudiente.parentesco) >= 0;
            a = { id: idAcu++, sede_id: e.sede_id, nombres: de(fem ? NOM_F : NOM_M), apellidos: e.apellidos.split(' ')[0] + ' ' + de(APE),
                tipo_documento: 'CC', numero_documento: String(entre(20000000, 1099999999)), genero: fem ? 'femenino' : 'masculino', telefono: e.acudiente.telefono,
                profesion: de(['Comerciante', 'Docente', 'Conductor', 'Ama de casa', 'Enfermera', 'Agricultor', 'Contadora', 'Independiente']),
                departamento: D_SEDE(e.sede_id).departamento, ciudad: D_SEDE(e.sede_id).ciudad, direccion: 'Carrera ' + entre(2, 30) + ' # ' + entre(1, 40) + '-' + entre(2, 80),
                activo: rnd() > 0.06, ultimo_acceso: rnd() < 0.2 ? null : haceDias(entre(0, 60), entre(1, 9)), creada: e.fecha_matricula };
            a.email = (a.nombres.split(' ')[0] + '.' + a.apellidos.split(' ')[0]).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') + entre(1, 99) + '@gmail.com';
            acudientes.push(a);
        }
        e.acudiente_id = a.id;
        /* Si es hermano de otro estudiante del mismo acudiente, el parentesco es el mismo */
        var otro = hermano && estudiantes.find(function (x) { return x !== e && x.acudiente_id === a.id; });
        e.parentesco = otro ? otro.parentesco : e.acudiente.parentesco;
    });
    function D_SEDE(id) { return sedes.find(function (x) { return x.id === id; }); }

    /* ── Recibos (recaudos): suman los ingresos de cada mes de cada sede ── */
    var MEDIOS = ['Efectivo', 'Efectivo', 'Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta'];
    var VALORES = [150000, 180000, 210000, 240000, 280000, 310000, 350000, 420000, 520000];
    var recaudos = [];
    sedes.forEach(function (s) {
        var pagan = estudiantes.filter(function (e) { return e.sede_id === s.id && e.estado !== 'sin_matricula'; });
        var alDia = pagan.filter(function (e) { return e.pago !== 'vencido'; });
        s.ingresos.forEach(function (total, i) {
            var inicio = mesAtras(5 - i), esActual = i === 5;
            var ultimoDia = esActual ? HOY.getDate() : new Date(inicio.getFullYear(), inicio.getMonth() + 1, 0).getDate();
            var resta = total;
            while (resta > 0) {
                var v = resta < 600000 ? resta : de(VALORES);
                resta -= v;
                var dia = entre(1, ultimoDia);
                var f = new Date(inicio.getFullYear(), inicio.getMonth(), dia, entre(7, 17), entre(0, 59));
                if (f.getTime() > ahora) f = new Date(ahora - entre(1, 5) * 3600000);
                var medio = de(MEDIOS), est = de(i >= 3 ? alDia : pagan);   // quien está en mora no ha pagado en los últimos meses
                recaudos.push({ sede_id: s.id, est_id: est.id, fecha: iso(f), valor: v, medio: medio,
                    referencia: medio === 'Efectivo' ? '' : String(entre(10000000, 99999999)),
                    concepto: rnd() < 0.12 ? 'Derecho de matrícula' : 'Cuota ' + (est.plan === 'periodo' ? 'del periodo' : 'mensual'),
                    quien: registra(s.id, f), anulado: false });
            }
        });
        /* Algunos anulados (no cuentan en los ingresos) */
        for (var a = 0; a < 3; a++) {
            var fa = new Date(ahora - entre(2, 150) * DIA);
            recaudos.push({ sede_id: s.id, est_id: de(pagan).id, fecha: iso(fa), valor: de(VALORES), medio: 'Efectivo', referencia: '',
                concepto: 'Cuota mensual', quien: registra(s.id, fa), anulado: true,
                motivo: de(['El pago se registró dos veces', 'Se registró a otro estudiante', 'El valor quedó mal digitado']) });
        }
    });
    recaudos.sort(function (x, y) { return new Date(x.fecha) - new Date(y.fecha); });
    var consec = {};
    recaudos.forEach(function (r, i) {
        var y = new Date(r.fecha).getFullYear();
        consec[y] = (consec[y] || 400) + 1;
        r.id = i + 1;
        r.numero = 'RC-' + y + '-' + String(consec[y]).padStart(5, '0');
    });

    /* ── Egresos y devoluciones ── */
    var egresos = [], devoluciones = [], idEg = 1, ceN = {};
    function numeroCE(f) { var y = new Date(f).getFullYear(); ceN[y] = (ceN[y] || 60) + 1; return 'CE-' + y + '-' + String(ceN[y]).padStart(5, '0'); }
    for (var m = 5; m >= 0; m--) {
        sedes.forEach(function (s) {
            var ini = mesAtras(m), fin = new Date(ini.getFullYear(), ini.getMonth() + 1, 0);
            function egreso(dia, cat, ben, valor, medio) {
                var f = new Date(ini.getFullYear(), ini.getMonth(), dia, 10, 0);
                if (f.getTime() > ahora) return;
                egresos.push({ id: idEg++, numero: numeroCE(f), sede_id: s.id, fecha: iso(f), categoria: cat, beneficiario: ben, valor: valor, medio: medio, quien: registra(s.id, f).nombre });
            }
            egreso(5, 'Arriendo y servicios', 'Arriendo de la sede', s.arriendo, 'Transferencia');
            egreso(18, 'Arriendo y servicios', 'Servicios públicos', Math.round(s.arriendo * (0.12 + rnd() * 0.08) / 1000) * 1000, 'Transferencia');
            egreso(entre(6, 25), 'Materiales e insumos', de(['Papelería y fotocopias', 'Insumos de laboratorio', 'Material de práctica', 'Implementos de aseo']), entre(30, 180) * 10000, de(['Efectivo', 'Transferencia']));
            egreso(fin.getDate(), 'Nómina', 'Nómina docente del mes', Math.round(s.nomina * (0.94 + rnd() * 0.1) / 1000) * 1000, 'Transferencia');
        });
    }
    egresos.sort(function (x, y) { return new Date(x.fecha) - new Date(y.fecha); });
    [[1, 18, 350000, 'Retiro antes de iniciar clases'], [1, 64, 420000, 'Cuotas pagadas por adelantado al retirarse'], [2, 37, 180000, 'Retiro antes de iniciar clases'],
     [3, 95, 520000, 'Cuotas pagadas por adelantado al aplazar'], [4, 120, 240000, 'Retiro antes de iniciar clases']].forEach(function (d, i) {
        var sede = sedes[d[0] - 1], f = haceDias(d[1], 3);
        var est = estudiantes.filter(function (e) { return e.sede_id === sede.id && e.estado === 'retirado'; })[i % 2] ||
                  estudiantes.find(function (e) { return e.sede_id === sede.id; });
        devoluciones.push({ id: i + 1, numero: 'DV-' + new Date(f).getFullYear() + '-' + String(8 + i).padStart(5, '0'), sede_id: sede.id, est_id: est.id,
            fecha: f, valor: d[2], motivo: d[3], medio: de(['Transferencia', 'Efectivo', 'Nequi']), quien: registra(sede.id, f).nombre });
    });

    /* ── Comunicados ── */
    var comunicados = [
        { id: 1, titulo: 'Inscripciones abiertas para el primer semestre de 2027', mensaje: 'Ya están abiertas las inscripciones para los programas que inician en febrero. Cuéntale a tus familiares y amigos: los nuevos estudiantes referidos por un estudiante activo tienen 10 % de descuento en el derecho de matrícula.', sedes: 'todas', destino: 'todos', autor: { tipo: 'admin', nombre: YO }, estado: 'programado', publicado_en: haceDias(-6, -8), creado: haceDias(1, 2), destinatarios: 0, leidos: 0 },
        { id: 2, titulo: 'Horario de atención en diciembre', mensaje: 'Del 20 de diciembre al 10 de enero las sedes atienden de lunes a viernes de 8:00 a. m. a 12:00 m. Los pagos por transferencia se siguen recibiendo normalmente.', sedes: 'todas', destino: 'todos', autor: { tipo: 'admin', nombre: YO }, estado: 'borrador', publicado_en: null, creado: haceDias(0, 5), destinatarios: 0, leidos: 0 },
        { id: 3, titulo: 'Recordatorio: las cuotas vencen el día 10 de cada mes', mensaje: 'Recuerden que las cuotas vencen el día 10. Pueden pagar en la secretaría de su sede (efectivo o tarjeta) o por transferencia, Nequi o Daviplata enviando el comprobante.', sedes: 'todas', destino: 'estudiantes', autor: { tipo: 'admin', nombre: YO }, estado: 'publicado', publicado_en: haceDias(4, 2), creado: haceDias(4, 3), destinatarios: 296, leidos: 211 },
        { id: 4, titulo: 'Capacitación de docentes: evaluación por competencias', mensaje: 'El sábado tendremos una capacitación virtual para todos los docentes sobre evaluación por competencias. El enlace llegará al correo.', sedes: 'todas', destino: 'docentes', autor: { tipo: 'admin', nombre: YO }, estado: 'publicado', publicado_en: haceDias(9, 4), creado: haceDias(9, 5), destinatarios: 31, leidos: 29 },
        { id: 5, titulo: 'Nuevo procedimiento para registrar devoluciones', mensaje: 'A partir de este mes toda devolución debe llevar el motivo completo y el medio por el que se entregó el dinero. Revisen el instructivo adjunto.', sedes: 'todas', destino: 'secretarias', autor: { tipo: 'admin', nombre: YO }, estado: 'publicado', publicado_en: haceDias(15, 1), creado: haceDias(15, 2), destinatarios: 5, leidos: 4 },
        { id: 6, titulo: 'Salida pedagógica de Hotelería al Salto del Tequendama', mensaje: 'Los estudiantes de Servicios Hoteleros y Turísticos tendrán salida pedagógica. Deben llevar el uniforme completo y el permiso firmado si son menores.', sedes: [2], destino: 'estudiantes', autor: { tipo: 'secretaria', nombre: 'Marcela Gutiérrez' }, estado: 'programado', publicado_en: haceDias(-3, -9), creado: haceDias(0, 5), destinatarios: 0, leidos: 0 },
        { id: 7, titulo: 'Entrega de boletines del primer módulo', mensaje: 'Los acudientes pueden recoger los boletines en la secretaría de la sede de lunes a viernes.', sedes: [1], destino: 'acudientes', autor: { tipo: 'secretaria', nombre: 'Paola Andrea Rincón' }, estado: 'publicado', publicado_en: haceDias(6, 3), creado: haceDias(6, 4), destinatarios: 22, leidos: 15 },
        { id: 8, titulo: 'Práctica en el laboratorio de enfermería', mensaje: 'El jueves la práctica es en el laboratorio del segundo piso. Lleguen 15 minutos antes.', sedes: [1], destino: 'grupo', grupo: 'Servicios Geriátricos · Grupo B', autor: { tipo: 'docente', nombre: 'Natalia Beltrán Soto' }, estado: 'publicado', publicado_en: haceDias(2, 6), creado: haceDias(2, 6), destinatarios: 21, leidos: 17 },
        { id: 9, titulo: 'Simulacro de evacuación', mensaje: 'El viernes a las 10:00 a. m. hay simulacro de evacuación en la sede. El punto de encuentro es el parque principal.', sedes: [3], destino: 'todos', autor: { tipo: 'secretaria', nombre: 'Jhon Mario Cárdenas' }, estado: 'publicado', publicado_en: haceDias(12, 2), creado: haceDias(13, 1), destinatarios: 104, leidos: 61 },
        { id: 10, titulo: 'Cambio de salón para Cocina Nacional e Internacional', mensaje: 'Desde la próxima semana las clases de cocina son en la cocina industrial del primer piso.', sedes: [4], destino: 'estudiantes', autor: { tipo: 'admin', nombre: YO }, estado: 'publicado', publicado_en: haceDias(20, 5), creado: haceDias(20, 6), destinatarios: 37, leidos: 22 },
        { id: 11, titulo: 'Jornada de vacunación en la sede', mensaje: 'La Secretaría de Salud estará en la sede con jornada de vacunación gratuita.', sedes: [2, 3], destino: 'todos', autor: { tipo: 'admin', nombre: YO }, estado: 'archivado', publicado_en: haceDias(40, 4), creado: haceDias(41, 1), destinatarios: 142, leidos: 98, motivo_archivo: 'La jornada ya pasó' },
        { id: 12, titulo: 'Fiesta de fin de año', mensaje: '¡Todos invitados a la integración de fin de año! Traigan un plato típico para compartir.', sedes: [1], destino: 'grupo', grupo: 'Auxiliar Contable · Grupo A', autor: { tipo: 'docente', nombre: 'Hernán Murillo Acosta' }, estado: 'publicado', publicado_en: haceDias(1, 7), creado: haceDias(1, 7), destinatarios: 19, leidos: 6 }
    ];

    /* ── Auditoría: lo que hicieron las secretarías y el Admin ── */
    var auditoria = [];
    function evento(dias, horas, sedeId, quien, rol, tipo, entidad, texto) {
        auditoria.push({ fecha: haceDias(dias, horas), sede_id: sedeId, quien: quien, rol: rol, tipo: tipo, entidad: entidad, texto: texto });
    }
    recaudos.forEach(function (r) {
        if (ahora - new Date(r.fecha) > 90 * DIA) return;
        var e = estudiantes[r.est_id - 1], nombre = e.nombres + ' ' + e.apellidos;
        auditoria.push({ fecha: r.fecha, sede_id: r.sede_id, quien: r.quien.nombre, rol: r.quien.rol, tipo: 'crear', entidad: 'Pago',
            texto: 'Registró un pago de $ ' + r.valor.toLocaleString('es-CO') + ' de ' + nombre + ' (' + r.numero + ').' });
        if (r.anulado) auditoria.push({ fecha: iso(new Date(new Date(r.fecha).getTime() + 3 * 3600000)), sede_id: r.sede_id, quien: r.quien.nombre, rol: r.quien.rol, tipo: 'anular', entidad: 'Pago',
            texto: 'Anuló el recibo ' + r.numero + ' de ' + nombre + ': ' + r.motivo.toLowerCase() + '.' });
    });
    estudiantes.forEach(function (e) {
        if (!e.fecha_matricula || ahora - new Date(e.fecha_matricula) > 90 * DIA) return;
        var f = e.fecha_matricula + 'T' + dosDig(entre(8, 16)) + ':' + dosDig(entre(0, 59)) + ':00', q = registra(e.sede_id, f);
        auditoria.push({ fecha: iso(new Date(f)), sede_id: e.sede_id, quien: q.nombre, rol: q.rol, tipo: 'crear', entidad: 'Matrícula',
            texto: 'Matriculó a ' + e.nombres + ' ' + e.apellidos + ' en ' + e.programa + ', ' + e.grupo + '.' });
    });
    egresos.forEach(function (g) {
        if (ahora - new Date(g.fecha) > 90 * DIA) return;
        var q = registra(g.sede_id, g.fecha);
        auditoria.push({ fecha: g.fecha, sede_id: g.sede_id, quien: q.nombre, rol: q.rol, tipo: 'crear', entidad: 'Egreso',
            texto: 'Registró el egreso ' + g.numero + ' por $ ' + g.valor.toLocaleString('es-CO') + ' (' + g.categoria.toLowerCase() + ').' });
    });
    evento(0, 5, 2, 'Marcela Gutiérrez', 'secretaria', 'crear', 'Comunicado', 'Programó el comunicado «Salida pedagógica de Hotelería al Salto del Tequendama» para los estudiantes de la sede.');
    evento(0, 5, null, YO, 'admin', 'crear', 'Comunicado', 'Guardó el borrador del comunicado «Horario de atención en diciembre» para todas las sedes.');
    evento(1, 2, null, YO, 'admin', 'crear', 'Comunicado', 'Programó el comunicado «Inscripciones abiertas para el primer semestre de 2027» para todas las sedes.');
    evento(2, 3, 1, YO, 'admin', 'crear', 'Secretaría', 'Creó la cuenta de secretaría de Sandra Milena Bermúdez Rojas en la sede Bogotá.');
    evento(3, 1, 1, 'Paola Andrea Rincón', 'secretaria', 'crear', 'Grupo', 'Creó el grupo D de Técnico Laboral en Auxiliar Contable y Administrativo (inicia el 1 de diciembre).');
    evento(4, 3, 1, 'Paola Andrea Rincón', 'secretaria', 'editar', 'Matrícula', 'Aplazó la matrícula de Diego Fernando León: viaje por trabajo.');
    evento(5, 6, 2, 'Marcela Gutiérrez', 'secretaria', 'crear', 'Docente', 'Registró a la docente Natalia Beltrán Soto.');
    evento(7, 2, 3, 'Jhon Mario Cárdenas', 'secretaria', 'editar', 'Horario', 'Cambió el horario del Grupo A de Técnico Laboral en Electromecánica.');
    evento(11, 4, 4, YO, 'admin', 'editar', 'Matrícula', 'Cambió de grupo a Andrés Felipe Gil (de Grupo A a Grupo B) desde la sede La Dorada.');
    evento(15, 1, null, YO, 'admin', 'crear', 'Comunicado', 'Publicó el comunicado «Nuevo procedimiento para registrar devoluciones» para las secretarías.');
    evento(30, 2, null, YO, 'admin', 'editar', 'Configuración', 'Cambió la cuota del sistema de $ 1.800 a $ 2.000 por mes.');
    evento(40, 5, 3, YO, 'admin', 'editar', 'Sede', 'Editó la dirección de la sede Guaduas.');
    evento(52, 1, 4, YO, 'admin', 'desactivar', 'Secretaría', 'Desactivó la cuenta de secretaría de Ricardo Salamanca Peña (terminó su contrato).');
    evento(60, 3, 2, 'Marcela Gutiérrez', 'secretaria', 'eliminar', 'Comunicado', 'Eliminó el borrador «Clase de prueba de cocina».');
    evento(95, 2, 3, YO, 'admin', 'crear', 'Secretaría', 'Creó la cuenta de secretaría de Jhon Mario Cárdenas Ortiz en la sede Guaduas.');
    auditoria.sort(function (x, y) { return new Date(y.fecha) - new Date(x.fecha); });
    auditoria.forEach(function (a, i) { a.id = i + 1; });

    /* ── Foro: un foro general por sede (categorías reales de ForoTema::CATEGORIAS) ── */
    var foro = [], idTema = 1, idMsj = 1;
    function autorDe(sedeId, rol) {
        if (rol === 'estudiante') { var e = de(estudiantes.filter(function (x) { return x.sede_id === sedeId && x.estado === 'activo'; })); return e.nombres + ' ' + e.apellidos.split(' ')[0]; }
        if (rol === 'acudiente') { var a = de(estudiantes.filter(function (x) { return x.sede_id === sedeId && x.acudiente; })); return a ? a.acudiente.nombre : 'Acudiente'; }
        if (rol === 'docente') { var d = de(docentes.filter(function (x) { return x.sedes.indexOf(sedeId) >= 0 && x.estado === 'activo'; })); return d.nombres + ' ' + d.apellidos.split(' ')[0]; }
        if (rol === 'secretaria') { var s2 = secretarias.find(function (x) { return x.sede_id === sedeId && x.activo && x.ultimo_acceso; }); return s2 ? nombreCorto(s2) : YO; }
        return YO;
    }
    function tema(sedeId, categoria, titulo, dias, mensajes, extra) {
        var t = Object.assign({ id: idTema++, sede_id: sedeId, categoria: categoria, titulo: titulo, fijado: false, cerrado: false, mensajes: [] }, extra || {});
        mensajes.forEach(function (m, i) {
            var rol = m[0] === 'secretaria' && !secretarias.some(function (x) { return x.sede_id === sedeId && x.activo; }) ? 'admin' : m[0];
            t.mensajes.push({ id: idMsj++, rol: rol, autor: autorDe(sedeId, rol), texto: m[1], fecha: haceDias(Math.max(0, dias - i), entre(1, 9)),
                oculto: m[2] && m[2].oculto ? m[2].oculto : null, reportes: m[2] && m[2].reportes ? m[2].reportes.map(function (r) { return { por: autorDe(sedeId, 'estudiante'), motivo: r, estado: 'pendiente' }; }) : [] });
        });
        foro.push(t);
    }
    tema(1, 'Trámites y pagos', '¿Hasta cuándo se puede pagar el derecho de matrícula?', 6, [['estudiante', 'Hola, ¿hasta qué día tengo para pagar el derecho de matrícula? Mi grupo empieza en diciembre.'], ['secretaria', 'Hola. El derecho de matrícula vence el día que empiezan las clases de tu grupo. Puedes pagarlo en la secretaría o por transferencia.'], ['estudiante', '¡Gracias!']]);
    tema(1, 'Dudas académicas', 'Material de Codificación CIE-10', 4, [['estudiante', '¿Alguien tiene el material de la clase del jueves de Codificación CIE-10?'], ['docente', 'Lo subí al grupo. Repasen los capítulos 1 a 5 para el taller.'], ['estudiante', 'Profe, ¿el taller es individual o en parejas?'], ['docente', 'En parejas.']], { fijado: true });
    tema(1, 'Vida en la sede', 'Se venden rifas para la integración', 2, [['estudiante', 'Vendo boletas para la rifa del grupo, valen $ 5.000. ¡Escríbanme al WhatsApp 3001112233!', { reportes: ['Publicidad o ventas', 'Comparte datos personales'] }], ['estudiante', 'Yo compro dos.']]);
    tema(1, 'Prácticas y salidas', 'Uniforme para la práctica clínica', 9, [['acudiente', 'Buenas tardes, ¿qué uniforme necesita mi hija para la práctica clínica?'], ['secretaria', 'Buenas tardes. Uniforme antifluido azul, zapatos blancos cerrados y carné visible.']], { cerrado: true });
    tema(1, 'Dudas académicas', 'Este profesor no sabe explicar', 1, [['estudiante', 'Este profesor es pésimo, no sabe nada y nadie le entiende. Deberían echarlo.', { reportes: ['Irrespeto a una persona'] }]]);
    tema(2, 'Prácticas y salidas', 'Salida pedagógica al Salto del Tequendama', 3, [['secretaria', 'La salida es el sábado a las 6:00 a. m. desde la sede. Los menores deben traer el permiso firmado.'], ['acudiente', '¿Cuánto cuesta el transporte?'], ['secretaria', 'Está incluido. Solo deben llevar refrigerio.'], ['estudiante', '¿Podemos llevar a un acompañante?']], { fijado: true });
    tema(2, 'Trámites y pagos', '¿Puedo pagar la cuota por Nequi?', 8, [['estudiante', '¿Se puede pagar la cuota por Nequi?'], ['secretaria', 'Sí, por transferencia, Nequi o Daviplata. Envía el comprobante para registrar el pago.']]);
    tema(2, 'Vida en la sede', 'Grupo de estudio los sábados', 5, [['estudiante', 'Armemos grupo de estudio los sábados en la biblioteca municipal para Psicopatología.'], ['estudiante', 'Me apunto.'], ['estudiante', 'Yo también, ¿a qué hora?']]);
    tema(3, 'Vida en la sede', 'Simulacro de evacuación: punto de encuentro', 12, [['secretaria', 'El simulacro es el viernes a las 10:00 a. m. El punto de encuentro es la esquina del parque principal, frente a la sede.'], ['docente', 'Los grupos de la tarde también participan.']], { cerrado: true });
    tema(3, 'Dudas académicas', 'Horas de práctica en cocina profesional', 3, [['estudiante', '¿Cuántas horas de práctica en cocina profesional debemos completar?'], ['docente', 'Son 800 horas en total. Llevamos el registro en la planilla.']]);
    tema(3, 'Trámites y pagos', 'Me cobraron dos veces la cuota', 1, [['estudiante', 'Me aparece la cuota de octubre cobrada dos veces, ¿a quién le escribo?'], ['estudiante', 'Esto es un robo, siempre hacen lo mismo con la plata de uno.', { reportes: ['Información falsa'] }]]);
    tema(4, 'Dudas académicas', 'Cambio de salón para Cocina', 7, [['docente', 'Desde la próxima semana las clases de cocina son en la cocina industrial del primer piso.'], ['estudiante', 'Perfecto, gracias profe.']]);
    tema(4, 'Vida en la sede', 'Alguien encontró un cargador', 2, [['estudiante', 'Se me quedó un cargador negro en el salón 3, ¿alguien lo vio?'], ['admin', 'Hola. Quedó en la secretaría de la sede, lo puedes reclamar en la mañana.']]);
    tema(4, 'Trámites y pagos', 'Constancia de estudio para el trabajo', 4, [['acudiente', '¿Cómo pido una constancia de estudio para mi hijo?'], ['estudiante', 'Mejor pregunten por WhatsApp, aquí nadie responde. Les dejo mi número 3159998877.', { oculto: { motivo: 'Comparte datos personales', por: YO, fecha: haceDias(3, 2) } }], ['admin', 'La constancia se expide en la secretaría de la sede mientras la matrícula esté vigente. Tarda un día hábil.']]);

    /* ── Configuración (datos de la institución reales) ── */
    var configuracion = {
        institucion: {
            nombre: 'Escuela Nacional de Educación ESAT', sigla: 'ESAT', nit: '900820764-1',
            licencia: '5850', fecha_licencia: '2019-09-23', entidad: 'Secretaría de Educación de Cundinamarca',
            telefono: '3152957588', telefono2: '3123773144', correo: ''
        },
        cuota_sistema: 2000,
        seguridad: { intentos_maximos: 5, dias_inactividad: 90 },
        bloqueadas: [
            { id: 1, nombre: 'Brayan Stiven Ortiz Gil', rol: 'Estudiante', sede_id: 1, motivo: 'intentos_fallidos', desde: haceDias(0, 6) },
            { id: 2, nombre: 'Gloria Patiño Acosta', rol: 'Acudiente', sede_id: 2, motivo: 'inactividad', desde: haceDias(12, 1) },
            { id: 3, nombre: 'Kevin Murillo Garzón', rol: 'Estudiante', sede_id: 3, motivo: 'intentos_fallidos', desde: haceDias(1, 2) },
            { id: 4, nombre: 'Hernán Quintero Rueda', rol: 'Docente', sede_id: 4, motivo: 'manual', desde: haceDias(26, 4) }
        ]
    };

    var D = {
        hoy: iso(HOY), yo: YO, escuelas: escuelas, programas: programas, docentes: docentes, acudientes: acudientes, grupos: grupos, foro: foro, sedes: sedes, secretarias: secretarias, estudiantes: estudiantes,
        recaudos: recaudos, egresos: egresos, devoluciones: devoluciones, comunicados: comunicados,
        auditoria: auditoria, configuracion: configuracion
    };

    /* ── Lo guardado en esta pestaña (sessionStorage) ── */
    var CLAVE = 'esat.general.ejemplo';
    function leer() { try { return JSON.parse(sessionStorage.getItem(CLAVE)) || {}; } catch (e) { return {}; } }
    function escribir(g) { try { sessionStorage.setItem(CLAVE, JSON.stringify(g)); } catch (e) { /* sin almacenamiento: solo en memoria */ } }
    var guardado = leer();
    ['sedes', 'secretarias', 'comunicados', 'programas', 'escuelas', 'foro', 'docentes', 'acudientes', 'estudiantes'].forEach(function (col) {
        (guardado[col] || []).forEach(function (reg) {
            var i = D[col].findIndex(function (x) { return x.id === reg.id; });
            if (i >= 0) D[col][i] = Object.assign({}, D[col][i], reg); else D[col].push(reg);
        });
    });
    if (guardado.configuracion) D.configuracion = Object.assign({}, D.configuracion, guardado.configuracion);
    (guardado.auditoria || []).forEach(function (a) { D.auditoria.unshift(a); });
    D.sedes.forEach(function (s) {
        ['estudiantes', 'estudiantesAnt', 'grupos', 'gruposAnt', 'gruposPorIniciar', 'cupos', 'ocupados', 'ingresoMesAntAlDia', 'nomina', 'arriendo'].forEach(function (k) { if (s[k] == null) s[k] = 0; });
        if (!s.ingresos) s.ingresos = [0, 0, 0, 0, 0, 0];
        if (!s.mora) s.mora = { valor: 0, estudiantes: 0 };
        if (!s.moraAnt) s.moraAnt = { valor: 0, estudiantes: 0 };
        if (!s.programas) s.programas = [];
    });
    D.auditoria.forEach(function (a, i) { a.id = i + 1; });

    /* La ficha del estudiante muestra siempre el acudiente vinculado (nombre y celular actuales) */
    function sincronizar() {
        D.estudiantes.forEach(function (e) {
            var a = e.acudiente_id && D.acudientes.find(function (x) { return x.id === e.acudiente_id; });
            e.acudiente = a ? { id: a.id, nombre: a.nombres + ' ' + a.apellidos, parentesco: e.parentesco || '', telefono: a.telefono } : null;
            if (e.fecha_nacimiento) {
                var n = new Date(e.fecha_nacimiento + 'T12:00:00'), h = new Date(), ed = h.getFullYear() - n.getFullYear();
                if (h.getMonth() < n.getMonth() || (h.getMonth() === n.getMonth() && h.getDate() < n.getDate())) ed--;
                e.edad = ed;
            }
        });
    }
    sincronizar();

    window.GEN_DATOS = D;
    window.GEN_API = {
        /* Crea o reemplaza un registro de sedes / secretarias / comunicados. Devuelve una Promise como un fetch. */
        guardar: function (coleccion, registro, textoAuditoria, tipo, sedeId) {
            return new Promise(function (ok) {
                setTimeout(function () {
                    var g = leer();
                    if (!registro.id) registro.id = Math.max.apply(null, D[coleccion].map(function (x) { return x.id; }).concat([0])) + 1;
                    g[coleccion] = (g[coleccion] || []).filter(function (x) { return x.id !== registro.id; }).concat([registro]);
                    var i = D[coleccion].findIndex(function (x) { return x.id === registro.id; });
                    if (i >= 0) D[coleccion][i] = Object.assign({}, D[coleccion][i], registro); else D[coleccion].push(registro);
                    if (textoAuditoria) registrarAuditoria(g, tipo || 'editar', coleccion, textoAuditoria, sedeId);
                    escribir(g);
                    sincronizar();
                    ok(registro);
                }, 250);
            });
        },
        guardarConfiguracion: function (cambios, textoAuditoria) {
            return new Promise(function (ok) {
                setTimeout(function () {
                    var g = leer();
                    g.configuracion = Object.assign({}, g.configuracion || {}, cambios);
                    D.configuracion = Object.assign({}, D.configuracion, cambios);
                    if (textoAuditoria) registrarAuditoria(g, 'editar', 'configuracion', textoAuditoria, null);
                    escribir(g);
                    ok(D.configuracion);
                }, 250);
            });
        },
        reiniciar: function () { try { sessionStorage.removeItem(CLAVE); } catch (e) { /* nada que borrar */ } }
    };
    function registrarAuditoria(g, tipo, coleccion, texto, sedeId) {
        var ENT = { sedes: 'Sede', secretarias: 'Secretaría', comunicados: 'Comunicado', configuracion: 'Configuración', programas: 'Programa', escuelas: 'Escuela', foro: 'Foro', docentes: 'Docente', acudientes: 'Acudiente', estudiantes: 'Estudiante' };
        var a = { fecha: new Date().toISOString(), sede_id: sedeId == null ? null : sedeId, quien: YO, rol: 'admin', tipo: tipo, entidad: ENT[coleccion], texto: texto };
        g.auditoria = (g.auditoria || []).concat([a]);
        D.auditoria.unshift(a);
        D.auditoria.forEach(function (x, i) { x.id = i + 1; });
    }
})();
