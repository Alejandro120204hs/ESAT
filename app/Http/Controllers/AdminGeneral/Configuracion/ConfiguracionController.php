<?php

namespace App\Http\Controllers\AdminGeneral\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Configuracion\InstitucionRequest;
use App\Models\Finanzas\Matricula;
use App\Models\Institucional\Configuracion;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\Sistema\BloqueoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Configuración de todo ESAT: datos de la institución, cuota del sistema, reglas de acceso y cuentas bloqueadas. */
class ConfiguracionController extends Controller
{
    private const ETIQUETAS = [
        'nombre' => 'nombre', 'sigla' => 'sigla', 'nit' => 'NIT', 'licencia' => 'licencia', 'fecha_licencia' => 'fecha de la licencia',
        'entidad' => 'entidad', 'telefono' => 'teléfono', 'telefono2' => 'otro teléfono', 'correo' => 'correo',
    ];

    public function index(BloqueoService $bloqueos): View
    {
        $bloqueadas = User::whereNotNull('bloqueado_en')->orderByDesc('bloqueado_en')->get()
            ->filter(fn (User $u) => $bloqueos->seBloquea($u))
            ->map(fn (User $u) => [
                'id' => $u->id, 'nombre' => trim($u->nombres.' '.$u->apellidos), 'rol' => $u->role->label(),
                'sede_id' => $u->sede_id, 'motivo' => $u->motivo_bloqueo, 'desde' => $u->bloqueado_en->toIso8601String(),
            ])->values();

        return view('admin_general.configuracion.index', ['datos' => [
            'sedes'         => Sede::orderBy('nombre')->get(['id', 'nombre'])->all(),
            'activos'       => Matricula::where('estado', 'activo')->distinct()->count('estudiante_id'),
            'configuracion' => [
                'institucion'   => Configuracion::institucion(),
                'cuota_sistema' => (int) round((float) (Configuracion::valor('cuota_sistema_mensual') ?? 0)),
                'seguridad'     => Configuracion::seguridad(),
                'bloqueadas'    => $bloqueadas,
                'motivos'       => BloqueoService::MOTIVOS,
            ],
        ]]);
    }

    public function institucion(InstitucionRequest $request): JsonResponse
    {
        $antes = Configuracion::institucion();
        $cambios = collect($request->validated())->map(fn ($v) => (string) ($v ?? ''))->filter(fn ($v, $k) => $v !== (string) $antes[$k]);
        if ($cambios->isEmpty()) {
            return response()->json(['mensaje' => 'No había cambios que guardar.']);
        }
        $fila = DB::transaction(fn () => $cambios->map(fn ($v, $k) => Configuracion::guardar('institucion_'.$k, $v))->first());
        Auditoria::registrar('editar', $fila, 'Cambió los datos de la institución: '.$cambios->keys()->map(fn ($k) => self::ETIQUETAS[$k])->join(', ', ' y ').'.');

        return response()->json(['mensaje' => 'Se guardaron los datos de la institución.']);
    }

    /** La cuota aplica a las cuotas que se generen desde ahora; las ya generadas no cambian. */
    public function cobros(Request $request): JsonResponse
    {
        $datos = $request->validate(
            ['cuota_sistema' => ['required', 'integer', 'min:0', 'max:100000', 'multiple_of:100']],
            ['cuota_sistema.multiple_of' => 'Usa un valor redondo (múltiplo de $ 100).', 'cuota_sistema.max' => 'Revisa el valor: es muy alto para una cuota del sistema.'],
            ['cuota_sistema' => 'cuota del sistema']
        );
        $antes = (int) round((float) (Configuracion::valor('cuota_sistema_mensual') ?? 0));
        if ($antes === (int) $datos['cuota_sistema']) {
            return response()->json(['mensaje' => 'No había cambios que guardar.']);
        }
        $fila = Configuracion::guardar('cuota_sistema_mensual', (int) $datos['cuota_sistema']);
        $plata = fn ($n) => '$ '.number_format($n, 0, ',', '.');
        Auditoria::registrar('editar', $fila, "Cambió la cuota del sistema de {$plata($antes)} a {$plata((int) $datos['cuota_sistema'])} por mes.");

        return response()->json(['mensaje' => 'La cuota del sistema quedó en '.$plata((int) $datos['cuota_sistema']).' por mes.']);
    }

    public function seguridad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'intentos_maximos' => ['required', 'integer', 'between:3,10'],
            'dias_inactividad' => ['required', 'integer', 'between:30,365'],
        ], [], ['intentos_maximos' => 'intentos fallidos', 'dias_inactividad' => 'días sin entrar']);
        $antes = Configuracion::seguridad();
        $texto = [];
        $fila = null;
        foreach (['intentos_maximos' => 'intentos fallidos', 'dias_inactividad' => 'días sin entrar'] as $k => $etiqueta) {
            if ($antes[$k] !== (int) $datos[$k]) {
                $fila = Configuracion::guardar('seguridad_'.$k, (int) $datos[$k]);
                $texto[] = "{$etiqueta} de {$antes[$k]} a {$datos[$k]}";
            }
        }
        if (! $fila) {
            return response()->json(['mensaje' => 'No había cambios que guardar.']);
        }
        Auditoria::registrar('editar', $fila, 'Cambió las reglas de acceso: '.implode('; ', $texto).'.');

        return response()->json(['mensaje' => 'Se guardaron las reglas de acceso.']);
    }

    public function desbloquear(User $usuario, BloqueoService $bloqueos): JsonResponse
    {
        abort_unless($usuario->bloqueado_en && $bloqueos->seBloquea($usuario), 404);
        $bloqueos->desbloquear($usuario);
        $rol = mb_strtolower($usuario->role->label());
        Auditoria::registrar('activar', $usuario, "Desbloqueó la cuenta de {$usuario->name} ({$rol}).", $usuario->sede_id);

        return response()->json(['mensaje' => "Se desbloqueó la cuenta de {$usuario->name}."]);
    }
}
