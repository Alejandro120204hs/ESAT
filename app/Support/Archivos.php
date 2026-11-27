<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Nombres de los archivos que suben las personas (entregas, guías, material, adjuntos del foro y de comunicados).
 * Se guarda el nombre original para mostrarlo y descargarlo con él, pero limpio: sin caracteres de control (un TAB en el
 * nombre rompía la descarga con un error 500) ni barras, y con un largo máximo (auditoría del panel Estudiante, ES20).
 */
final class Archivos
{
    public static function nombre(UploadedFile $archivo, int $max = 150): string
    {
        $nombre = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', (string) $archivo->getClientOriginalName()) ?? '';
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            $nombre = 'archivo.'.($archivo->guessExtension() ?: $archivo->getClientOriginalExtension() ?: 'bin');
        }

        return mb_substr($nombre, 0, $max);
    }
}
