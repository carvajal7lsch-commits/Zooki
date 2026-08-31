<?php
/**
 * Términos de Uso de Zooki.
 *
 * El documento refleja el estado real del proyecto: un sistema en desarrollo
 * que aún no se ofrece comercialmente. Evita deliberadamente garantías de
 * servicio que hoy no pueden sostenerse, y deja claro que Zooki es una
 * herramienta de registro, no un sustituto del criterio profesional
 * veterinario, cuya responsabilidad sigue rigiéndose por la Ley 576 de 2000.
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

    <title>Términos de Uso | Zooki</title>
    <meta name="description" content="Términos y condiciones de uso del sistema de gestión veterinaria Zooki.">
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
                <h1 class="lp-legal__title">Términos de Uso</h1>
                <p class="lp-legal__lead">
                    Estas condiciones regulan el acceso y la utilización de Zooki. Al crear una cuenta
                    o usar el sistema, aceptas lo que aquí se establece.
                </p>
                <ul class="lp-legal__meta">
                    <li><i class="ri-calendar-line" aria-hidden="true"></i> Vigente desde el <?php echo $lp_vigencia; ?></li>
                    <li><i class="ri-map-pin-line" aria-hidden="true"></i> Legislación colombiana</li>
                    <li><i class="ri-price-tag-3-line" aria-hidden="true"></i> Versión <?php echo $lp_version; ?></li>
                </ul>
            </header>

            <div class="lp-legal__layout">

                <nav class="lp-legal__toc" aria-label="Índice del documento">
                    <p class="lp-legal__toc-title">Contenido</p>
                    <ol>
                        <li><a href="#objeto">Objeto y aceptación</a></li>
                        <li><a href="#estado">Estado del proyecto</a></li>
                        <li><a href="#servicio">Qué ofrece Zooki</a></li>
                        <li><a href="#cuenta">Tu cuenta</a></li>
                        <li><a href="#uso">Uso permitido y prohibido</a></li>
                        <li><a href="#clinica">Responsabilidad clínica</a></li>
                        <li><a href="#datos">Titularidad de la información</a></li>
                        <li><a href="#disponibilidad">Disponibilidad y responsabilidad</a></li>
                        <li><a href="#propiedad">Propiedad intelectual</a></li>
                        <li><a href="#terminacion">Suspensión y terminación</a></li>
                        <li><a href="#cambios">Cambios en los términos</a></li>
                        <li><a href="#ley">Ley aplicable</a></li>
                    </ol>
                </nav>

                <article class="lp-legal__doc" id="documento">

                    <section id="objeto">
                        <h2>Objeto y aceptación</h2>
                        <p>
                            Estos términos regulan la relación entre Zooki y las personas que acceden al
                            sistema, sea como personal de una clínica veterinaria o como propietarios de
                            mascotas.
                        </p>
                        <p>
                            Al crear una cuenta, marcar la casilla de aceptación o utilizar cualquier función
                            del sistema, manifiestas que has leído y aceptas estos términos y la
                            <a href="index.php?action=privacidad">Política de Tratamiento de Datos Personales</a>.
                            Si no estás de acuerdo con alguno de sus puntos, no debes usar el sistema.
                        </p>
                    </section>

                    <section id="estado">
                        <h2>Estado del proyecto</h2>
                        <div class="lp-legal__note">
                            <i class="ri-tools-line" aria-hidden="true"></i>
                            <p>
                                Zooki se encuentra <strong>en desarrollo activo</strong> y no se ofrece todavía
                                como servicio comercial. Se entrega en su estado actual, con fines de evaluación
                                y uso piloto, sin garantías de continuidad, disponibilidad ni ausencia de errores.
                            </p>
                        </div>
                        <p>
                            En consecuencia, no debe utilizarse como <strong>único repositorio</strong> de
                            información clínica crítica. Recomendamos mantener respaldos propios de la
                            información que consideres esencial mientras el sistema permanezca en esta etapa.
                        </p>
                    </section>

                    <section id="servicio">
                        <h2>Qué ofrece Zooki</h2>
                        <p>Zooki es una aplicación web de gestión para clínicas veterinarias que permite:</p>
                        <ul>
                            <li>Registrar pacientes, propietarios y su historial clínico.</li>
                            <li>Administrar la agenda de citas con control de disponibilidad.</li>
                            <li>Llevar el calendario de vacunación y desparasitación, con recordatorios automáticos.</li>
                            <li>Ofrecer a los propietarios un portal de consulta y agendamiento.</li>
                            <li>Generar documentos exportables, como la cartilla de salud en PDF.</li>
                        </ul>
                        <p>
                            El alcance de las funciones disponibles depende del rol asignado a cada cuenta.
                        </p>
                    </section>

                    <section id="cuenta">
                        <h2>Tu cuenta</h2>
                        <p>
                            Para usar el sistema necesitas una cuenta. Al crearla te comprometes a proporcionar
                            información <strong>veraz, completa y actualizada</strong>, y a mantenerla al día.
                        </p>
                        <p>
                            Eres responsable de la confidencialidad de tus credenciales y de toda actividad
                            realizada desde tu cuenta. Si detectas un acceso no autorizado, debes comunicarlo
                            de inmediato al canal de contacto.
                        </p>
                        <div class="lp-legal__note">
                            <i class="ri-user-shared-line" aria-hidden="true"></i>
                            <p>
                                <strong>No compartas tu cuenta.</strong> El sistema registra cada acción con el
                                usuario que la ejecutó; si varias personas usan las mismas credenciales, el
                                registro de auditoría deja de identificar correctamente quién hizo qué.
                            </p>
                        </div>
                    </section>

                    <section id="uso">
                        <h2>Uso permitido y prohibido</h2>
                        <p>El sistema debe usarse conforme a la ley y a su finalidad. Queda prohibido:</p>
                        <ul>
                            <li>Acceder o intentar acceder a información de pacientes o usuarios que no te corresponda.</li>
                            <li>Suplantar la identidad de otra persona o usar credenciales ajenas.</li>
                            <li>Alterar, interferir o sobrecargar deliberadamente el funcionamiento del sistema.</li>
                            <li>Extraer información de forma masiva o automatizada sin autorización.</li>
                            <li>Introducir contenido ilícito, ofensivo o que vulnere derechos de terceros.</li>
                            <li>Usar la información obtenida con finalidades distintas a la atención veterinaria.</li>
                        </ul>
                        <p>
                            Las conductas dirigidas contra la confidencialidad, integridad o disponibilidad de
                            los datos pueden constituir delito conforme a la <strong>Ley 1273 de 2009</strong>.
                        </p>
                    </section>

                    <section id="clinica">
                        <h2>Responsabilidad clínica</h2>
                        <p>
                            Zooki es una <strong>herramienta de registro y organización</strong>. No emite
                            diagnósticos, no prescribe tratamientos y no sustituye en ningún caso el criterio
                            del profesional veterinario.
                        </p>
                        <p>
                            Las decisiones clínicas, la exactitud de la información registrada y el cumplimiento
                            de los deberes profesionales —incluida la obligación de llevar y conservar la
                            historia clínica— corresponden al médico veterinario tratante, conforme a la
                            <strong>Ley 576 de 2000</strong>.
                        </p>
                        <p>
                            Los recordatorios de vacunación y desparasitación son una ayuda, no una garantía:
                            no eximen al propietario ni a la clínica de su deber de seguimiento.
                        </p>
                    </section>

                    <section id="datos">
                        <h2>Titularidad de la información</h2>
                        <p>
                            La información clínica y de contacto registrada en el sistema pertenece a la clínica
                            veterinaria y a los titulares de los datos, no a Zooki. El sistema actúa como medio
                            de almacenamiento y consulta.
                        </p>
                        <p>
                            El tratamiento de los datos personales se rige por la
                            <a href="index.php?action=privacidad">Política de Tratamiento de Datos Personales</a>,
                            que forma parte integral de estos términos.
                        </p>
                    </section>

                    <section id="disponibilidad">
                        <h2>Disponibilidad y responsabilidad</h2>
                        <p>
                            El sistema se ofrece sin garantía de disponibilidad ininterrumpida. Puede haber
                            interrupciones por mantenimiento, actualizaciones, fallos técnicos o causas ajenas
                            a nuestro control.
                        </p>
                        <p>
                            En la medida en que lo permita la ley colombiana, no asumimos responsabilidad por
                            perjuicios derivados del uso o de la imposibilidad de uso del sistema, de la pérdida
                            de información no respaldada, ni de decisiones tomadas con base en datos registrados
                            de forma incorrecta por los propios usuarios.
                        </p>
                        <p>
                            Nada en estos términos excluye la responsabilidad que no pueda limitarse conforme a
                            la legislación aplicable.
                        </p>
                    </section>

                    <section id="propiedad">
                        <h2>Propiedad intelectual</h2>
                        <p>
                            El código fuente, el diseño, la marca Zooki, su logotipo y su documentación están
                            protegidos por las normas de propiedad intelectual. Su uso, reproducción o
                            modificación fuera de lo permitido requiere autorización previa.
                        </p>
                        <p>
                            El uso del sistema no transfiere ningún derecho de propiedad intelectual al usuario.
                        </p>
                    </section>

                    <section id="terminacion">
                        <h2>Suspensión y terminación</h2>
                        <p>
                            Podemos suspender o cancelar una cuenta que incumpla estos términos, que ponga en
                            riesgo la seguridad del sistema o la información de terceros, o cuando lo exija una
                            autoridad competente.
                        </p>
                        <p>
                            El usuario puede solicitar en cualquier momento la desactivación de su cuenta a
                            través del canal de contacto. La supresión de los datos asociados se regirá por lo
                            previsto en la política de privacidad, incluidas las limitaciones derivadas del
                            deber de conservar la historia clínica.
                        </p>
                    </section>

                    <section id="cambios">
                        <h2>Cambios en los términos</h2>
                        <p>
                            Estos términos pueden actualizarse para reflejar cambios en el sistema o en la
                            normativa aplicable. Las modificaciones sustanciales se comunicarán a través de los
                            canales de contacto registrados antes de su entrada en vigencia.
                        </p>
                        <p>
                            El uso del sistema después de una actualización implica la aceptación de la nueva
                            versión.
                        </p>
                    </section>

                    <section id="ley">
                        <h2>Ley aplicable</h2>
                        <p>
                            Estos términos se rigen por la legislación de la República de Colombia. Cualquier
                            controversia se someterá a los jueces competentes del territorio colombiano.
                        </p>
                        <p>
                            Para cualquier consulta relacionada con estas condiciones puedes escribir a
                            <a href="mailto:zooki.vet@gmail.com">zooki.vet@gmail.com</a>.
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
