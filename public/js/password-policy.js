/**
 * HU-36 / RN-G10 — Política de contraseñas, lado cliente.
 *
 * Es un espejo de helpers/PoliticaPassword.php y existe solo para avisar
 * antes de enviar el formulario. LA VALIDACIÓN QUE MANDA ES LA DEL SERVIDOR:
 * allí está la lista completa de contraseñas comunes (helpers/passwords-comunes.txt)
 * y la comprobación contra los datos del titular. Aquí va una versión
 * reducida, suficiente para no prometerle al usuario que "Password1" sirve.
 *
 * Si se cambia la regla, hay que cambiarla en los dos archivos.
 */
window.ZOOKI_PASSWORD_MINIMO = 8;
window.ZOOKI_PASSWORD_TEXTO = 'Mínimo 8 caracteres, con mayúscula, minúscula y número';

/** Raíces más frecuentes. El servidor tiene la lista completa. */
window.ZOOKI_PASSWORD_COMUNES = [
    'password', 'passwd', 'contrasena', 'clave', 'qwerty', 'qwertyuiop', 'asdfgh',
    'abc', 'abcd', 'asdf', 'qwe', 'asd', 'zxc', 'qweasd', 'qazwsx',
    'zxcvbn', 'abcdef', 'abcdefgh', 'letmein', 'welcome', 'login', 'admin',
    'administrador', 'root', 'usuario', 'user', 'test', 'prueba', 'demo',
    'secret', 'master', 'monkey', 'dragon', 'shadow', 'sunshine', 'princess',
    'football', 'superman', 'batman', 'pokemon', 'computer', 'internet',
    'hola', 'holamundo', 'amor', 'teamo', 'tequiero', 'miamor', 'mivida',
    'familia', 'corazon', 'princesa', 'estrella', 'hermosa', 'bonita', 'cielo',
    'colombia', 'bogota', 'medellin', 'cali', 'neiva', 'huila', 'america',
    'nacional', 'millonarios', 'junior', 'futbol', 'seleccion',
    'zooki', 'veterinaria', 'veterinario', 'clinica', 'mascota', 'mascotas',
    'perro', 'perrito', 'gato', 'gatito', 'cachorro', 'firulais', 'animal'
];

/** Minúsculas, sin acentos ni símbolos, deshaciendo el leet (4→a, 0→o...). */
function zookiNormalizarPassword(texto) {
    var mapa = {
        'á':'a','é':'e','í':'i','ó':'o','ú':'u','ü':'u','ñ':'n',
        '4':'a','3':'e','1':'i','0':'o','5':'s','7':'t','8':'b','9':'g',
        '@':'a','$':'s','!':'i','|':'i','+':'t'
    };
    return texto.toLowerCase().replace(/./g, function (c) {
        return mapa[c] !== undefined ? mapa[c] : c;
    }).replace(/[^a-z0-9]/g, '');
}

/** true si la contraseña es una variante de alguna raíz común. */
window.esPasswordComun = function (password) {
    var base = password.toLowerCase();

    // Los dígitos del borde se quitan ANTES del leet: si no, el "1" final de
    // "Password1" se convierte en "i" y deja de parecerse a "password".
    var raices = [base];
    var sinFinal = base.replace(/[0-9]+$/, '');
    if (sinFinal && sinFinal !== base) raices.push(sinFinal);
    var sinInicio = base.replace(/^[0-9]+/, '');
    if (sinInicio && sinInicio !== base) raices.push(sinInicio);

    for (var i = 0; i < raices.length; i++) {
        var soloAlfa = raices[i].replace(/[^a-z0-9]/g, '');
        var conLeet = zookiNormalizarPassword(raices[i]);
        if (window.ZOOKI_PASSWORD_COMUNES.indexOf(soloAlfa) !== -1) return true;
        if (window.ZOOKI_PASSWORD_COMUNES.indexOf(conLeet) !== -1) return true;
    }

    return false;
};

/** Secuencias, corridas de teclado y caracteres repetidos. */
window.tienePatronTrivial = function (password) {
    // Dos formas: la normalizada NO sirve sola porque al traducir 1->i, 3->e,
    // 5->s convierte "12345" en letras y borra la secuencia antes de buscarla.
    var limpio = password.toLowerCase().replace(/[^a-z0-9]/g, '');

    return zookiPatronEn(limpio) || zookiPatronEn(zookiNormalizarPassword(password));
};

function zookiPatronEn(texto) {
    if (!texto) return false;

    if (/(.)\1{3,}/.test(texto)) return true;

    var racha = 1, sentido = 0;
    for (var i = 1; i < texto.length; i++) {
        var delta = texto.charCodeAt(i) - texto.charCodeAt(i - 1);
        if ((delta === 1 || delta === -1) && (sentido === 0 || sentido === delta)) {
            sentido = delta;
            racha++;
            if (racha >= 5) return true;
        } else {
            sentido = (delta === 1 || delta === -1) ? delta : 0;
            racha = sentido !== 0 ? 2 : 1;
        }
    }

    var filas = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', '1234567890'];
    for (var f = 0; f < filas.length; f++) {
        var fila = filas[f];
        var inversa = fila.split('').reverse().join('');
        for (var j = 0; j + 5 <= fila.length; j++) {
            if (texto.indexOf(fila.substr(j, 5)) !== -1) return true;
            if (texto.indexOf(inversa.substr(j, 5)) !== -1) return true;
        }
    }

    return false;
}

/**
 * @param {string} password
 * @returns {boolean} true si cumple la política
 */
window.esPasswordValida = function (password) {
    if (typeof password !== 'string') return false;
    if (password.length < window.ZOOKI_PASSWORD_MINIMO) return false;
    if (!/[a-záéíóúüñ]/.test(password)) return false;
    if (!/[A-ZÁÉÍÓÚÜÑ]/.test(password)) return false;
    if (!/[0-9]/.test(password)) return false;
    if (window.esPasswordComun(password)) return false;
    if (window.tienePatronTrivial(password)) return false;

    return true;
};

/** Motivo del rechazo, para mostrarlo en el formulario. */
window.motivoPasswordInvalida = function (password) {
    if (typeof password !== 'string' || password.length < window.ZOOKI_PASSWORD_MINIMO) {
        return 'Mínimo ' + window.ZOOKI_PASSWORD_MINIMO + ' caracteres';
    }
    if (!/[a-záéíóúüñ]/.test(password)) return 'Falta una letra minúscula';
    if (!/[A-ZÁÉÍÓÚÜÑ]/.test(password)) return 'Falta una letra mayúscula';
    if (!/[0-9]/.test(password)) return 'Falta un número';
    if (window.esPasswordComun(password)) return 'Es una contraseña demasiado conocida';
    if (window.tienePatronTrivial(password)) return 'Evita secuencias como "12345" o "abcde"';

    return null;
};
