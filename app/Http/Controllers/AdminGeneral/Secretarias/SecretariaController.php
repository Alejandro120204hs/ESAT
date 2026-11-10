<?php

namespace App\Http\Controllers\AdminGeneral\Secretarias;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminGeneral\Secretarias\SecretariaRequest;
use App\Models\Institucional\Sede;
use App\Models\Sistema\Auditoria;
use App\Models\User;
use App\Services\General\EstructuraService;
use App\Services\Sistema\SesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Cuentas de secretaría (rol admin) de todas las sedes. La clave inicial es el número de documento. */
class SecretariaController extends Controller
{
    public function index(EstructuraService $estructura): View
    {
        $sedes = collect($estructura->sedes())->map(fn ($s) => collect($s)->only(['id', 'nombre', 'ciudad', 'departamento']))->all();

        return view('admin_general.secretarias.index', ['datos' => [
            'sedes'       => $sedes,
            'secretarias' => $estructura->secretarias(),
        ]]);
    }

    public function store(SecretariaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $cuenta = User::create($datos + [
            'role'     => RolUsuario::Admin,
            'password' => Hash::make($datos['numero_documento']),
            'activo'   => true,
        ]);
        $sede = Sede::find($datos['sede_id']);
        Auditoria::registrar('crear', $cuenta, "Creó la cuenta de secretaría de {$cuenta->name} en la sede {$sede->nombre}.", $sede->id);

        return response()->json(['mensaje' => "Se creó la cuenta de {$cuenta->name}. Su clave inicial es su número de documento."], 201);
    }

    public function update(SecretariaRequest $request, User $secretaria): JsonResponse
    {
        $this->asegurarSecretaria($secretaria);
        $secretaria->fill($request->validated());
        if (! $secretaria->isDirty()) {
            return response()->json(['mensaje' => 'No había cambios que guardar.']);
        }
        $cambioSede = $secretaria->isDirty('sede_id');
        $secretaria->save();
        $sede = Sede::find($secretaria->sede_id);
        Auditoria::registrar('editar', $secretaria, "Editó la cuenta de secretaría de {$secretaria->name}".($cambioSede ? " y la pasó a la sede {$sede->nombre}." : '.'), $sede->id);

        return response()->json(['mensaje' => "Se guardaron los cambios de {$secretaria->name}."]);
    }

    /** Activa o desactiva la cuenta. Una desactivada pierde la sesión en su siguiente petición (EnsureAccountIsEnabled). */
    public function estado(User $secretaria): JsonResponse
    {
        $this->asegurarSecretaria($secretaria);
        $secretaria->update(['activo' => ! $secretaria->activo]);
        $accion = $secretaria->activo ? 'activar' : 'desactivar';
        $sede = $secretaria->sede;
        Auditoria::registrar($accion, $secretaria, ($secretaria->activo ? 'Activó' : 'Desactivó')." la cuenta de secretaría de {$secretaria->name} (sede {$sede?->nombre}).", $secretaria->sede_id);

        return response()->json(['mensaje' => ($secretaria->activo ? 'Se activó' : 'Se desactivó')." la cuenta de {$secretaria->name}."]);
    }

    /** La clave vuelve a ser el número de documento y se cierran sus sesiones abiertas (también "Recordarme"). */
    public function restablecerClave(User $secretaria, SesionesService $sesiones): JsonResponse
    {
        $this->asegurarSecretaria($secretaria);
        $sesiones->restablecerClave($secretaria);
        Auditoria::registrar('editar', $secretaria, "Restableció la clave de la secretaría {$secretaria->name}.", $secretaria->sede_id);

        return response()->json(['mensaje' => "La clave de {$secretaria->name} volvió a ser su número de documento."]);
    }

    /** Estas rutas solo tocan cuentas de secretaría: cualquier otra cuenta no existe para ellas. */
    private function asegurarSecretaria(User $u): void
    {
        abort_unless($u->role === RolUsuario::Admin, 404);
    }
}
