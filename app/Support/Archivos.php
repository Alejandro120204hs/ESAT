<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Nombres de los archivos que suben las personas (entregas, guías, material, adjuntos del foro y de comunicados).
 * Se guarda el nombre original para mostrarlo y descargarlo con él, pero limpio (auditoría del panel Estudiante, ES20, y
 * su revisión):
 * - sin caracteres de control ni de formato (\p{Cc}, \p{Cf}): un TAB rompía la descarga con un error 500, y el U+202E
 *   invierte lo que se ve ("tarea‮fdp.exe" se ve "tareaexe.pdf");
 * - sin barras;
 * - con una extensión de las aceptadas: si la del nombre no lo es, se usa la del contenido (la que revisa `mimes:`);
 * - con un largo máximo que no corta la extensión.
 */
final class Archivos
{
    /** Las extensiones que acepta alguna subida (cada una revisa su propia lista con `mimes:`). */
    private const EXTENSIONES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    public static function nombre(UploadedFile $archivo, int $max = 150): string
    {
        $nombre = preg_replace('/[\p{Cc}\p{Cf}\/\\\\]+/u', ' ', (string) $archivo->getClientOriginalName()) ?? '';
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        $real = strtolower((string) $archivo->guessExtension());
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $base = $ext !== '' ? mb_substr($nombre, 0, -(mb_strlen($ext) + 1)) : $nombre;
        if (! in_array($ext, self::EXTENSIONES, true)) {
            // Sin extensión aceptada en el nombre: la del contenido (o la que trae, si el contenido no dice nada)
            $base = $nombre;
            $ext = in_array($real, self::EXTENSIONES, true) ? $real : ($ext !== '' ? $ext : ($real ?: 'bin'));
            if (pathinfo($nombre, PATHINFO_EXTENSION) === $ext) {
                $base = mb_substr($nombre, 0, -(mb_strlen($ext) + 1));
            }
        }
        $base = trim($base, " .");
        if ($base === '') {
            $base = 'archivo';
        }

        return mb_substr($base, 0, max(1, $max - mb_strlen($ext) - 1)).'.'.$ext;
    }
}
