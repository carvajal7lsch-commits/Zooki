<?php
/**
 * Foto de perfil de la mascota: guardarla y borrar la que reemplaza.
 *
 * La usan el panel del personal (MascotaController) y el portal del
 * propietario (PropietarioController). Antes el portal tenía su propia copia
 * sin las correcciones M1-02 y M1-06: armaba el nombre del archivo con el
 * nombre de la mascota tal cual (una mascota llamada `../../algo` escribía
 * fuera de la carpeta) y solo miraba la extensión.
 */
final class FotoMascota
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private static function carpeta(): string
    {
        return __DIR__ . '/../public/uploads/mascotas/';
    }

    /**
     * Devuelve null si no se envió ninguna foto. Si algo falla, deja el motivo
     * en $error y devuelve false.
     *
     * M1-02 — El nombre lo genera el servidor; del nombre de la mascota solo
     * se conservan letras, dígitos y guiones.
     * M1-06 — Se comprueba que el archivo sea realmente una imagen y la
     * extensión se deduce del tipo real, no de lo que mande el cliente.
     *
     * @return string|false|null
     */
    public static function guardar(?array $archivo, string $nombreMascota, ?string &$error)
    {
        $error = null;

        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $motivos = [
                UPLOAD_ERR_INI_SIZE   => 'La foto excede el límite máximo de tamaño de archivo (5MB).',
                UPLOAD_ERR_FORM_SIZE  => 'La foto excede el límite máximo de tamaño de archivo (5MB).',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta una carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
                UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida del archivo.',
            ];
            $error = $motivos[$archivo['error']] ?? 'Error al subir el archivo.';
            return false;
        }

        if ($archivo['size'] > self::MAX_BYTES) {
            $error = 'La foto no debe superar los 5MB.';
            return false;
        }

        // El tipo sale del contenido, no del nombre ni del Content-Type que
        // manda el navegador, que el cliente controla por completo.
        $info = @getimagesize($archivo['tmp_name']);
        $extensionPorTipo = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
        ];
        if ($info === false || !isset($extensionPorTipo[$info[2]])) {
            $error = 'Solo se permiten imágenes en formato JPG o PNG.';
            return false;
        }
        $ext = $extensionPorTipo[$info[2]];

        $nombreArchivo = time() . '_' . bin2hex(random_bytes(4)) . '_' . self::etiqueta($nombreMascota) . '.' . $ext;

        $destino = self::carpeta();
        if (!is_dir($destino) && !mkdir($destino, 0755, true) && !is_dir($destino)) {
            $error = 'No se pudo preparar la carpeta de imágenes en el servidor.';
            return false;
        }

        if (!move_uploaded_file($archivo['tmp_name'], $destino . $nombreArchivo)) {
            $error = 'Error al guardar la imagen en el servidor. Verifique permisos.';
            return false;
        }

        return $nombreArchivo;
    }

    /** Del nombre de la mascota solo sobrevive lo que es seguro en una ruta. */
    public static function etiqueta(string $nombreMascota): string
    {
        $etiqueta = preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', $nombreMascota));
        $etiqueta = substr($etiqueta, 0, 40);
        return $etiqueta === '' ? 'mascota' : $etiqueta;
    }

    /**
     * Borra la foto que acaba de quedar reemplazada (M1-16).
     *
     * basename() descarta cualquier separador de ruta que trajera el valor
     * guardado, así que el borrado nunca sale de la carpeta de subidas. Un
     * fallo aquí no deshace nada: la ficha ya se guardó bien.
     */
    public static function eliminarAnterior(?string $anterior, string $nueva): void
    {
        if ($anterior === null || $anterior === '' || $anterior === $nueva) {
            return;
        }

        $ruta = self::carpeta() . basename($anterior);
        if (is_file($ruta) && !@unlink($ruta)) {
            error_log('No se pudo borrar la foto anterior de la mascota: ' . $ruta);
        }
    }
}
