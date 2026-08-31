<?php
/**
 * Política de Cookies y Almacenamiento Local.
 *
 * El inventario que aparece aquí no es genérico: corresponde a lo que el
 * código realmente guarda en el navegador. La cookie de sesión de PHP es la
 * única propia; el resto son claves de `localStorage` que el usuario activa
 * de forma deliberada. Al no existir cookies de analítica ni publicitarias,
 * el sistema no muestra banner de consentimiento: no habría nada que
 * consentir, y la obligación aplicable en Colombia es informar.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/App.php';

$lp_logged_in = isset($_SESSION['usuario_doc']);
$lp_cta_url   = $lp_logged_in ? 'index.php?action=dashboard' : 'index.php?action=login';
$lp_cta_text  = $lp_logged_in ? 'Ir al panel' : 'Iniciar sesión';
$lp_version   = App::assetVersion();
$lp_vigencia  = '31 de agosto de 2026';
?>
<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Política de Cookies | Zooki</title>
    <meta name="description" content="Qué guarda Zooki en tu navegador y por qué: cookies de sesión, preferencias locales y servicios de terceros.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#0052FF">

    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link rel="apple-touch-icon" href="img/icon_blue.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/landing.css?v=<?php echo $lp_version; ?>">
</head>
<body class="landing-body">

    <a class="lp-skip-link" href="#documento">Saltar al contenido principal</a>

    <?php include __DIR__ . '/../landing/partials/nav.php'; ?>

    <main class="lp-legal">
        <div class="lp-container">

            <header class="lp-legal__header">
                <h1 class="lp-legal__title">Política de Cookies</h1>
                <p class="lp-legal__lead">
                    Qué guarda Zooki en tu navegador, para qué sirve cada cosa y cómo puedes controlarlo.
                    Este documento enumera lo que el sistema utiliza realmente, no una lista genérica.
                </p>
                <ul class="lp-legal__meta">
                    <li><i class="ri-calendar-line" aria-hidden="true"></i> Vigente desde el <?php echo $lp_vigencia; ?></li>
                    <li><i class="ri-shield-check-line" aria-hidden="true"></i> Sin analítica ni publicidad</li>
                    <li><i class="ri-price-tag-3-line" aria-hidden="true"></i> Versión <?php echo $lp_version; ?></li>
                </ul>
            </header>

            <div class="lp-legal__layout">

                <nav class="lp-legal__toc" aria-label="Índice del documento">
                    <p class="lp-legal__toc-title">Contenido</p>
                    <ol>
                        <li><a href="#que-son">Qué son</a></li>
                        <li><a href="#cookies">Cookies que usamos</a></li>
                        <li><a href="#local">Almacenamiento local</a></li>
                        <li><a href="#terceros">Servicios de terceros</a></li>
                        <li><a href="#no-usamos">Lo que no usamos</a></li>
                        <li><a href="#control">Cómo controlarlo</a></li>
                        <li><a href="#cambios">Cambios</a></li>
                    </ol>
                </nav>

                <article class="lp-legal__doc" id="documento">

                    <section id="que-son">
                        <h2>Qué son las cookies y el almacenamiento local</h2>
                        <p>
                            Una <strong>cookie</strong> es un archivo pequeño que un sitio web guarda en tu
                            navegador y que se envía de vuelta al servidor en cada visita. El
                            <strong>almacenamiento local</strong> cumple una función parecida, pero la
                            información se queda en tu equipo y no viaja al servidor.
                        </p>
                        <p>
                            Zooki usa ambos mecanismos únicamente para que el sistema funcione y para recordar
                            preferencias que tú mismo eliges.
                        </p>
                    </section>

                    <section id="cookies">
                        <h2>Cookies que usamos</h2>
                        <p>Zooki instala <strong>una sola cookie propia</strong>:</p>
                        <dl class="lp-legal__defs">
                            <div>
                                <dt>PHPSESSID — cookie de sesión</dt>
                                <dd>
                                    Identifica tu sesión mientras navegas, de modo que el sistema sepa que sigues
                                    siendo tú al pasar de una página a otra. Sin ella no es posible iniciar sesión.
                                    Es una cookie técnica estrictamente necesaria y se elimina al cerrar el navegador
                                    o al cerrar sesión.
                                </dd>
                            </div>
                        </dl>
                        <div class="lp-legal__note">
                            <i class="ri-shield-keyhole-line" aria-hidden="true"></i>
                            <p>
                                Esta cookie no contiene datos personales: guarda solo un identificador aleatorio.
                                La información de tu sesión permanece en el servidor.
                            </p>
                        </div>
                    </section>

                    <section id="local">
                        <h2>Almacenamiento local</h2>
                        <p>
                            Estas claves se guardan en tu navegador y <strong>nunca se envían al servidor</strong>.
                            Todas responden a una acción tuya y puedes revertirlas en cualquier momento:
                        </p>
                        <dl class="lp-legal__defs">
                            <div>
                                <dt>zooki_remember y zooki_remember_doc</dt>
                                <dd>
                                    Guardan que activaste la casilla «Recuérdame» y tu número de documento, para no
                                    tener que teclearlo en cada visita. Se borran al desmarcar la casilla.
                                    <strong>Nunca se guarda la contraseña.</strong>
                                </dd>
                            </div>
                            <div>
                                <dt>petViewPreference y ownerViewPreference</dt>
                                <dd>
                                    Recuerdan si prefieres ver los listados de pacientes y propietarios en cuadrícula
                                    o en lista, para que el sistema se abra como lo dejaste.
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section id="terceros">
                        <h2>Servicios de terceros</h2>
                        <p>
                            Algunas partes del sistema cargan recursos de proveedores externos. Estos proveedores
                            pueden recibir tu dirección IP y datos técnicos del navegador, y aplicar sus propias
                            políticas:
                        </p>
                        <ul>
                            <li><strong>Google Identity Services</strong> — solo interviene si eliges iniciar sesión con tu cuenta de Google, y puede instalar cookies propias necesarias para esa autenticación.</li>
                            <li><strong>Google Fonts</strong> — provee las tipografías de la interfaz.</li>
                            <li><strong>jsDelivr y cdnjs</strong> — proveen las librerías de iconos y componentes visuales.</li>
                        </ul>
                        <p>
                            Ninguno de estos servicios se utiliza para perfilar usuarios ni para publicidad.
                        </p>
                    </section>

                    <section id="no-usamos">
                        <h2>Lo que no usamos</h2>
                        <p>Para que quede explícito, Zooki <strong>no utiliza</strong>:</p>
                        <ul>
                            <li>Cookies de analítica o medición de audiencia.</li>
                            <li>Cookies publicitarias ni píxeles de seguimiento.</li>
                            <li>Rastreo entre sitios o elaboración de perfiles de comportamiento.</li>
                            <li>Venta o cesión de datos de navegación a terceros.</li>
                        </ul>
                        <div class="lp-legal__note">
                            <i class="ri-information-line" aria-hidden="true"></i>
                            <p>
                                Por eso el sistema <strong>no muestra un banner de consentimiento de cookies</strong>:
                                las únicas que existen son técnicamente necesarias para funciones que tú mismo
                                solicitas. Si en el futuro se incorporan herramientas de analítica o publicidad,
                                se implementará el mecanismo de consentimiento correspondiente y se actualizará
                                este documento.
                            </p>
                        </div>
                    </section>

                    <section id="control">
                        <h2>Cómo controlarlo</h2>
                        <p>
                            Puedes revisar, bloquear o eliminar cookies y almacenamiento local desde la
                            configuración de tu navegador, normalmente en el apartado de privacidad o de datos
                            de sitios. También puedes desmarcar «Recuérdame» en la pantalla de acceso para
                            borrar de inmediato los datos asociados.
                        </p>
                        <div class="lp-legal__note">
                            <i class="ri-alert-line" aria-hidden="true"></i>
                            <p>
                                Si bloqueas la cookie de sesión, <strong>no podrás iniciar sesión</strong> en Zooki:
                                el sistema no tendría forma de reconocerte entre una página y la siguiente.
                            </p>
                        </div>
                    </section>

                    <section id="cambios">
                        <h2>Cambios</h2>
                        <p>
                            Este documento se actualizará si cambian las tecnologías que el sistema utiliza. La
                            fecha de vigencia que aparece al inicio indica la última revisión.
                        </p>
                        <p>
                            Para cualquier consulta puedes escribir a
                            <a href="mailto:zooki.vet@gmail.com">zooki.vet@gmail.com</a>. El tratamiento de tus
                            datos personales se detalla en la
                            <a href="index.php?action=privacidad">Política de Tratamiento de Datos Personales</a>.
                        </p>
                    </section>

                </article>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../landing/partials/footer.php'; ?>

    <script src="js/landing.js?v=<?php echo $lp_version; ?>"></script>
</body>
</html>
