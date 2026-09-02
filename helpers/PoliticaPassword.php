<?php
/**
 * HU-36 (VD-SEG-07) / RN-G10 — Politica unica de contrasenas.
 *
 * Antes cada flujo tenia la suya: el registro pedia 6 caracteres, el
 * restablecimiento 8, y los formularios declaraban minlength="6" en el HTML.
 * Ninguno exigia complejidad. La politica real del sistema terminaba siendo
 * la del flujo mas debil.
 *
 * La politica tiene dos mitades, y la segunda es la que de verdad importa:
 *
 *   1. Composicion (largo + mayuscula + minuscula + numero). Mide la FORMA.
 *   2. Adivinabilidad: lista de uso masivo, datos personales del propio
 *      usuario y patrones triviales. Mide si alguien la acertaria.
 *
 * Solo con la primera pasaban "Password1", "Qwerty12" o "Zooki2026": cumplen
 * el patron al pie de la letra y estan entre las primeras que prueba
 * cualquier ataque de diccionario, justamente porque tantos sitios exigen
 * esa forma. NIST SP 800-63B recomienda por eso apoyarse en listas de
 * bloqueo antes que en reglas de composicion; aqui se conservan ambas
 * porque el criterio de la HU pide complejidad explicita.
 */
class PoliticaPassword
{
    /** Longitud minima exigida en todos los flujos. */
    public const MINIMO = 8;

    /** Tope razonable: password_hash con bcrypt ignora lo que pase de 72 bytes. */
    public const MAXIMO = 72;

    /** Corridas de este largo o mas se consideran triviales ("12345", "abcde"). */
    private const LARGO_SECUENCIA = 5;

    /** Repeticiones de este largo o mas ("aaaa"). */
    private const LARGO_REPETICION = 4;

    /** Lista de bloqueo, cargada una sola vez. */
    private static $comunes = null;

    /**
     * Valida una contrasena.
     *
     * @param mixed $password
     * @param array $datosPersonales documento, nombre y correo del titular,
     *        para impedir que use sus propios datos como clave
     * @return string|null null si cumple; si no, el motivo para mostrar
     */
    public static function validar($password, array $datosPersonales = []): ?string
    {
        if (!is_string($password) || $password === '') {
            return 'La contraseña es obligatoria.';
        }

        // El tope se mide en BYTES, no en caracteres, porque el limite de
        // bcrypt es en bytes: una contrasena de 40 letras acentuadas ocupa 76.
        // El mensaje lo dice asi para que no parezca un error del sistema
        // cuando alguien escribe menos de 72 caracteres y aun asi se pasa.
        if (strlen($password) > self::MAXIMO) {
            return 'La contraseña es demasiado larga (el límite son ' . self::MAXIMO
                 . ' bytes; las letras acentuadas y los emoji ocupan más de uno).';
        }

        // mb_strlen y no strlen: con acentos o emoji, strlen cuenta bytes y
        // dejaria pasar contrasenas mas cortas de lo que parecen.
        if (mb_strlen($password) < self::MINIMO) {
            return 'La contraseña debe tener al menos ' . self::MINIMO . ' caracteres.';
        }

        if (!preg_match('/[a-záéíóúüñ]/u', $password)) {
            return 'La contraseña debe incluir al menos una letra minúscula.';
        }

        if (!preg_match('/[A-ZÁÉÍÓÚÜÑ]/u', $password)) {
            return 'La contraseña debe incluir al menos una letra mayúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'La contraseña debe incluir al menos un número.';
        }

        if (self::esDemasiadoComun($password)) {
            return 'Esa contraseña es demasiado conocida. Elige una que no aparezca en listas de uso común.';
        }

        if (self::tienePatronTrivial($password)) {
            return 'Evita secuencias o caracteres repetidos (como "12345", "abcde" o "aaaa").';
        }

        if (self::usaDatosPersonales($password, $datosPersonales)) {
            return 'La contraseña no puede contener tu documento, tu nombre ni tu correo.';
        }

        return null;
    }

    /** true si cumple la politica. */
    public static function esValida($password, array $datosPersonales = []): bool
    {
        return self::validar($password, $datosPersonales) === null;
    }

    /** Texto para los formularios, para no repetirlo en cada vista. */
    public static function descripcion(): string
    {
        return 'Mínimo ' . self::MINIMO . ' caracteres, con mayúscula, minúscula y número. '
             . 'No uses contraseñas comunes ni tus datos personales.';
    }

    // ---------------------------------------------------------------------
    // Adivinabilidad
    // ---------------------------------------------------------------------

    /**
     * Reduce la contrasena a su raiz comparable: minusculas, sin acentos, sin
     * simbolos, y deshaciendo las sustituciones tipicas (4->a, 3->e, 0->o...).
     * Asi "P4ssw0rd!" y "Password" colapsan al mismo texto.
     */
    private static function normalizar(string $password): string
    {
        $texto = mb_strtolower($password, 'UTF-8');

        $mapa = [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            '4'=>'a','3'=>'e','1'=>'i','0'=>'o','5'=>'s','7'=>'t','8'=>'b','9'=>'g',
            '@'=>'a','$'=>'s','!'=>'i','|'=>'i','+'=>'t',
        ];
        $texto = strtr($texto, $mapa);

        return preg_replace('/[^a-z0-9]/u', '', $texto);
    }

    /**
     * Compara contra la lista de bloqueo. Se prueban tres formas: la
     * contrasena tal cual en minusculas, su version normalizada, y esa misma
     * sin los digitos del final, que es como se construye "Password1" o
     * "Zooki2026" a partir de una raiz conocida.
     */
    private static function esDemasiadoComun(string $password): bool
    {
        $lista = self::listaComunes();
        if (!$lista) return false;

        foreach (self::variantes($password) as $candidato) {
            if (isset($lista[$candidato])) return true;
        }

        return false;
    }

    /**
     * Formas comparables de una misma contrasena.
     *
     * El orden importa: los digitos del borde se quitan ANTES de deshacer el
     * leet, porque si no "P4ssw0rd1" se convierte en "passwordi" (el 1 final
     * pasa a ser i) y deja de parecerse a "password". Primero se recorta el
     * sufijo numerico, despues se traducen las sustituciones.
     */
    private static function variantes(string $password): array
    {
        $base = mb_strtolower($password, 'UTF-8');

        $raices = [$base];
        $sinFinal = rtrim($base, '0123456789');
        if ($sinFinal !== '' && $sinFinal !== $base) $raices[] = $sinFinal;
        $sinInicio = ltrim($base, '0123456789');
        if ($sinInicio !== '' && $sinInicio !== $base) $raices[] = $sinInicio;

        $variantes = [];
        foreach ($raices as $raiz) {
            // Sin leet: para las que ya son texto plano ("contrasena").
            $variantes[] = preg_replace('/[^a-z0-9]/u', '', $raiz);
            // Con leet: para las disfrazadas ("m3d3ll1n").
            $variantes[] = self::normalizar($raiz);
        }

        return array_filter(array_unique($variantes));
    }

    /**
     * Secuencias ascendentes o descendentes, corridas de teclado y
     * repeticiones del mismo caracter.
     */
    private static function tienePatronTrivial(string $password): bool
    {
        // Se revisan dos formas. La normalizada NO sirve por si sola: al
        // traducir 1->i, 3->e, 5->s convierte "12345" en letras y borra la
        // secuencia justo antes de buscarla. Por eso se mira primero el texto
        // sin traducir (digitos intactos) y despues el normalizado, que atrapa
        // los disfraces del tipo "4bcd3f".
        $limpio = preg_replace('/[^a-z0-9]/u', '', mb_strtolower($password, 'UTF-8'));

        return self::patronEn($limpio) || self::patronEn(self::normalizar($password));
    }

    private static function patronEn(string $texto): bool
    {
        if ($texto === '') return false;

        // Mismo caracter repetido.
        if (preg_match('/(.)\1{' . (self::LARGO_REPETICION - 1) . ',}/u', $texto)) {
            return true;
        }

        // Secuencias en el alfabeto o en los digitos, en cualquier sentido.
        $largo = mb_strlen($texto);
        $racha = 1;
        $sentido = 0;
        for ($i = 1; $i < $largo; $i++) {
            $delta = ord($texto[$i]) - ord($texto[$i - 1]);
            if (($delta === 1 || $delta === -1) && ($sentido === 0 || $sentido === $delta)) {
                $sentido = $delta;
                $racha++;
                if ($racha >= self::LARGO_SECUENCIA) return true;
            } else {
                $racha = 1;
                $sentido = ($delta === 1 || $delta === -1) ? $delta : 0;
                if ($sentido !== 0) $racha = 2;
            }
        }

        // Corridas de teclado (qwerty, asdfgh...), en ambos sentidos.
        $filas = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', '1234567890'];
        foreach ($filas as $fila) {
            $filaInversa = strrev($fila);
            for ($i = 0; $i + self::LARGO_SECUENCIA <= strlen($fila); $i++) {
                $trozo = substr($fila, $i, self::LARGO_SECUENCIA);
                if (strpos($texto, $trozo) !== false) return true;

                $trozoInverso = substr($filaInversa, $i, self::LARGO_SECUENCIA);
                if (strpos($texto, $trozoInverso) !== false) return true;
            }
        }

        return false;
    }

    /**
     * Impide usar el propio documento, nombre o correo como contrasena, que
     * es lo primero que prueba quien ya tiene los datos de la persona (y en
     * una clinica esos datos los ve todo el personal de recepcion).
     */
    private static function usaDatosPersonales(string $password, array $datos): bool
    {
        $texto = self::normalizar($password);
        if ($texto === '') return false;

        foreach ($datos as $dato) {
            if (!is_string($dato) || $dato === '') continue;

            // El correo se parte en el usuario, antes de la arroba.
            if (strpos($dato, '@') !== false) {
                $dato = substr($dato, 0, strpos($dato, '@'));
            }

            // Nombres compuestos: cada parte cuenta por separado.
            foreach (preg_split('/[\s._-]+/u', $dato) as $parte) {
                $parte = self::normalizar($parte);

                // Menos de 4 caracteres da falsos positivos ("ana", "luz").
                if (mb_strlen($parte) < 4) continue;

                if (strpos($texto, $parte) !== false) return true;
            }
        }

        return false;
    }

    /**
     * Carga la lista de bloqueo, indexada para consultarla en O(1).
     * Si el archivo falta, la validacion sigue con las demas reglas en vez
     * de bloquear el registro.
     */
    private static function listaComunes(): array
    {
        if (self::$comunes !== null) return self::$comunes;

        self::$comunes = [];
        $ruta = __DIR__ . '/passwords-comunes.txt';

        if (!is_readable($ruta)) {
            error_log('HU-36: no se pudo leer helpers/passwords-comunes.txt; '
                    . 'la lista de contrasenas comunes queda sin aplicar.');
            return self::$comunes;
        }

        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#') continue;

            self::$comunes[self::normalizar($linea)] = true;
        }

        return self::$comunes;
    }
}
