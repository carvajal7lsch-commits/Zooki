<?php
/**
 * Política de Tratamiento de Datos Personales.
 *
 * El orden y los contenidos siguen el artículo 2.2.2.25.3.1 del Decreto 1074
 * de 2015, que fija de forma taxativa lo que debe incluir toda política:
 * identificación del responsable, tratamientos y finalidades, derechos del
 * titular, área de atención, procedimiento de consultas y reclamos, y fecha
 * de vigencia. Los plazos citados son los de los artículos 14 y 15 de la
 * Ley 1581 de 2012.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/App.php';

// Contexto compartido con los parciales de navegación y pie de página.
$lp_logged_in = isset($_SESSION['usuario_doc']);
$lp_cta_url   = $lp_logged_in ? 'index.php?action=dashboard' : 'index.php?action=login';
$lp_cta_text  = $lp_logged_in ? 'Ir al panel' : 'Iniciar sesión';
$lp_version   = App::assetVersion();

// Fecha de entrada en vigencia del documento (exigida por el decreto).
$lp_vigencia  = '31 de agosto de 2026';
?>
<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Política de Tratamiento de Datos Personales | Zooki</title>
    <meta name="description" content="Política de tratamiento de datos personales de Zooki, conforme a la Ley 1581 de 2012 y al Decreto 1074 de 2015 de Colombia.">
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
                <h1 class="lp-legal__title">Política de Tratamiento de Datos Personales</h1>
                <p class="lp-legal__lead">
                    Este documento describe cómo Zooki recolecta, usa, almacena y protege los datos
                    personales de quienes utilizan el sistema, y explica los derechos que la ley
                    colombiana reconoce a sus titulares.
                </p>
                <ul class="lp-legal__meta">
                    <li><i class="ri-calendar-line" aria-hidden="true"></i> Vigente desde el <?php echo $lp_vigencia; ?></li>
                    <li><i class="ri-scales-3-line" aria-hidden="true"></i> Ley 1581 de 2012 · Decreto 1074 de 2015</li>
                    <li><i class="ri-price-tag-3-line" aria-hidden="true"></i> Versión <?php echo $lp_version; ?></li>
                </ul>
            </header>

            <div class="lp-legal__layout">

                <nav class="lp-legal__toc" aria-label="Índice del documento">
                    <p class="lp-legal__toc-title">Contenido</p>
                    <ol>
                        <li><a href="#responsable">Responsable del tratamiento</a></li>
                        <li><a href="#definiciones">Definiciones</a></li>
                        <li><a href="#datos">Datos que recolectamos</a></li>
                        <li><a href="#finalidades">Finalidades del tratamiento</a></li>
                        <li><a href="#autorizacion">Autorización del titular</a></li>
                        <li><a href="#derechos">Derechos del titular</a></li>
                        <li><a href="#procedimiento">Consultas y reclamos</a></li>
                        <li><a href="#seguridad">Medidas de seguridad</a></li>
                        <li><a href="#terceros">Encargados y terceros</a></li>
                        <li><a href="#menores">Datos de menores de edad</a></li>
                        <li><a href="#vigencia">Vigencia</a></li>
                    </ol>
                </nav>

                <article class="lp-legal__doc" id="documento">

                    <section id="responsable">
                        <h2>Responsable del tratamiento</h2>
                        <p>
                            Zooki es un sistema de gestión clínica para veterinarias desarrollado en el marco
                            del programa de Análisis y Desarrollo de Software del SENA, Centro Tecnológico de
                            la Amazonia (ficha 3142784), en Neiva, Huila, Colombia.
                        </p>
                        <p>
                            Actualmente el sistema se encuentra en desarrollo y no opera comercialmente. El
                            equipo del proyecto actúa como <strong>Responsable del Tratamiento</strong> de los
                            datos registrados durante esta etapa.
                        </p>
                        <div class="lp-legal__note">
                            <i class="ri-information-line" aria-hidden="true"></i>
                            <p>
                                <strong>Cuando una clínica veterinaria adopte Zooki</strong>, esa clínica pasará
                                a ser la Responsable del Tratamiento de los datos de sus clientes, por ser quien
                                decide qué información recolecta y con qué finalidad. Zooki actuará entonces como
                                Encargado del Tratamiento, procesando esos datos por cuenta de la clínica y
                                conforme a sus instrucciones.
                            </p>
                        </div>
                        <h3>Canal de atención</h3>
                        <p>
                            Las peticiones, consultas y reclamos relacionados con datos personales se atienden
                            a través del correo electrónico de contacto del proyecto:
                            <a href="mailto:zooki.vet@gmail.com">zooki.vet@gmail.com</a>. El área encargada de
                            su trámite es el equipo de desarrollo de Zooki.
                        </p>
                    </section>

                    <section id="definiciones">
                        <h2>Definiciones</h2>
                        <p>Los siguientes términos se emplean con el significado que les da la Ley 1581 de 2012:</p>
                        <dl class="lp-legal__defs">
                            <div>
                                <dt>Titular</dt>
                                <dd>Persona natural cuyos datos personales son objeto de tratamiento. En Zooki son, principalmente, los propietarios de mascotas y el personal de la clínica.</dd>
                            </div>
                            <div>
                                <dt>Dato personal</dt>
                                <dd>Cualquier información vinculada o que pueda asociarse a una persona natural determinada o determinable.</dd>
                            </div>
                            <div>
                                <dt>Tratamiento</dt>
                                <dd>Cualquier operación sobre datos personales: recolección, almacenamiento, uso, circulación o supresión.</dd>
                            </div>
                            <div>
                                <dt>Responsable del Tratamiento</dt>
                                <dd>Quien decide sobre la base de datos y el tratamiento de los datos.</dd>
                            </div>
                            <div>
                                <dt>Encargado del Tratamiento</dt>
                                <dd>Quien realiza el tratamiento de datos por cuenta del Responsable.</dd>
                            </div>
                            <div>
                                <dt>Autorización</dt>
                                <dd>Consentimiento previo, expreso e informado del titular para que sus datos sean tratados.</dd>
                            </div>
                        </dl>
                    </section>

                    <section id="datos">
                        <h2>Datos que recolectamos</h2>
                        <h3>Datos de identificación y contacto</h3>
                        <ul>
                            <li>Tipo y número de documento de identidad.</li>
                            <li>Nombre completo.</li>
                            <li>Correo electrónico y número de teléfono.</li>
                            <li>Credenciales de acceso, almacenadas siempre de forma cifrada.</li>
                            <li>Identificador de cuenta de Google, únicamente si el usuario elige ese método de acceso.</li>
                        </ul>

                        <h3>Datos de la operación clínica</h3>
                        <p>
                            El sistema registra información de las mascotas (especie, raza, sexo, peso, fecha de
                            nacimiento, fotografía) y su historial médico: consultas, diagnósticos, tratamientos,
                            vacunas, desparasitaciones y archivos clínicos adjuntos.
                        </p>
                        <div class="lp-legal__note">
                            <i class="ri-heart-pulse-line" aria-hidden="true"></i>
                            <p>
                                Los datos clínicos de una mascota <strong>no son datos personales de su
                                propietario</strong>, porque no describen a una persona natural. Sin embargo,
                                están vinculados a él dentro del sistema, por lo que reciben el mismo nivel de
                                protección y control de acceso.
                            </p>
                        </div>

                        <h3>Datos de auditoría</h3>
                        <p>
                            Por razones de seguridad y trazabilidad, el sistema registra los inicios de sesión y
                            las operaciones de creación, modificación y eliminación, junto con el usuario que las
                            ejecutó, la fecha y el estado anterior del dato modificado.
                        </p>

                        <h3>Datos sensibles</h3>
                        <p>
                            Zooki <strong>no recolecta datos sensibles</strong> en el sentido del artículo 5 de la
                            Ley 1581 de 2012: no solicita información sobre salud humana, origen racial o étnico,
                            orientación política, convicciones religiosas, datos biométricos ni vida sexual.
                        </p>
                    </section>

                    <section id="finalidades">
                        <h2>Finalidades del tratamiento</h2>
                        <p>Los datos personales recolectados se tratan exclusivamente para:</p>
                        <ul>
                            <li>Crear y administrar la cuenta de usuario, y autenticar su acceso al sistema.</li>
                            <li>Identificar al propietario de cada mascota y vincularlo con su historial clínico.</li>
                            <li>Gestionar la agenda de citas, incluyendo su confirmación, reprogramación y cancelación.</li>
                            <li>Enviar recordatorios de vacunación, desparasitación y citas programadas.</li>
                            <li>Generar los documentos clínicos que el propietario o la clínica soliciten, como la cartilla de salud en PDF.</li>
                            <li>Mantener el registro de auditoría exigido por razones de seguridad y trazabilidad.</li>
                            <li>Atender las peticiones, consultas y reclamos que presente el titular.</li>
                        </ul>
                        <p>
                            Los datos <strong>no se venden, ceden ni comparten</strong> con terceros para fines
                            publicitarios ni comerciales.
                        </p>
                    </section>

                    <section id="autorizacion">
                        <h2>Autorización del titular</h2>
                        <p>
                            De acuerdo con el artículo 9 de la Ley 1581 de 2012, el tratamiento de datos requiere
                            la autorización previa, expresa e informada del titular. En Zooki esa autorización se
                            obtiene mediante una casilla de aceptación explícita en el formulario de registro,
                            que remite a esta política y que no viene marcada por defecto.
                        </p>
                        <p>
                            El titular puede <strong>revocar su autorización</strong> en cualquier momento,
                            siguiendo el procedimiento descrito más adelante.
                        </p>
                    </section>

                    <section id="derechos">
                        <h2>Derechos del titular</h2>
                        <p>El artículo 8 de la Ley 1581 de 2012 reconoce a todo titular los siguientes derechos:</p>
                        <ul>
                            <li>Conocer, actualizar y rectificar sus datos personales.</li>
                            <li>Solicitar prueba de la autorización otorgada.</li>
                            <li>Ser informado sobre el uso que se ha dado a sus datos.</li>
                            <li>Presentar quejas ante la Superintendencia de Industria y Comercio por infracciones a la ley.</li>
                            <li>Revocar la autorización y solicitar la supresión de sus datos.</li>
                            <li>Acceder de forma gratuita a los datos que hayan sido objeto de tratamiento.</li>
                        </ul>
                        <div class="lp-legal__note">
                            <i class="ri-alert-line" aria-hidden="true"></i>
                            <p>
                                La supresión de datos no procede cuando exista un deber legal o contractual de
                                conservarlos. En particular, la historia clínica veterinaria debe conservarse
                                conforme a las obligaciones del ejercicio profesional previstas en la Ley 576 de
                                2000. En esos casos se informará al titular el motivo de la conservación.
                            </p>
                        </div>
                    </section>

                    <section id="procedimiento">
                        <h2>Consultas y reclamos</h2>
                        <p>
                            El titular, sus causahabientes o su representante pueden dirigir sus solicitudes al
                            canal de atención indicado en la primera sección, identificándose y describiendo con
                            claridad lo que solicitan.
                        </p>

                        <h3>Consultas</h3>
                        <p>
                            Se atienden en un plazo máximo de <strong>diez (10) días hábiles</strong> contados
                            desde su recepción. Si no fuera posible atenderlas en ese término, se informará al
                            interesado el motivo y la fecha de respuesta, que no superará los
                            <strong>cinco (5) días hábiles</strong> siguientes al vencimiento del primer plazo.
                        </p>

                        <h3>Reclamos</h3>
                        <p>
                            Cuando el titular considere que la información debe corregirse, actualizarse o
                            suprimirse, o advierta un incumplimiento de la ley, puede presentar un reclamo, que
                            se atenderá en un plazo máximo de <strong>quince (15) días hábiles</strong>. Si no
                            fuera posible, se informará el motivo y la fecha de respuesta, que no superará los
                            <strong>ocho (8) días hábiles</strong> siguientes.
                        </p>
                        <p>
                            Si el reclamo llega incompleto, se solicitará al interesado que lo subsane dentro de
                            los cinco (5) días siguientes. Transcurridos dos (2) meses sin respuesta, se entenderá
                            que ha desistido.
                        </p>
                        <p>
                            El titular puede acudir a la Superintendencia de Industria y Comercio únicamente una
                            vez agotado este trámite ante el Responsable.
                        </p>
                    </section>

                    <section id="seguridad">
                        <h2>Medidas de seguridad</h2>
                        <p>
                            Zooki aplica medidas técnicas y administrativas orientadas a garantizar la seguridad
                            de la información y evitar su adulteración, pérdida, consulta o uso no autorizado:
                        </p>
                        <ul>
                            <li>Las contraseñas se almacenan cifradas mediante <em>hashing</em> bcrypt con salt, y no son legibles por ningún usuario del sistema.</li>
                            <li>El acceso está segmentado por roles: cada perfil alcanza únicamente la información que le corresponde.</li>
                            <li>Los archivos clínicos exigen sesión activa y autorización sobre el paciente; no son accesibles mediante enlace directo.</li>
                            <li>Las operaciones de escritura se validan con tokens contra falsificación de peticiones, y los intentos de acceso están limitados en frecuencia.</li>
                            <li>Toda operación queda registrada en un log de auditoría con su autor y el estado anterior del dato.</li>
                            <li>La comunicación con el sistema debe realizarse sobre canales cifrados (HTTPS).</li>
                        </ul>
                    </section>

                    <section id="terceros">
                        <h2>Encargados y terceros</h2>
                        <p>
                            Para operar, Zooki se apoya en servicios de terceros que pueden tratar datos por
                            cuenta del Responsable:
                        </p>
                        <ul>
                            <li><strong>Google Identity Services</strong>, únicamente cuando el usuario elige iniciar sesión con su cuenta de Google.</li>
                            <li><strong>Servicio de correo electrónico</strong>, para el envío de recordatorios, confirmaciones de cita y recuperación de contraseña.</li>
                            <li><strong>Proveedor de alojamiento</strong>, donde reside la base de datos del sistema.</li>
                        </ul>
                        <p>
                            Estos servicios acceden a la información mínima necesaria para prestar su función y no
                            están autorizados a usarla con finalidades propias.
                        </p>
                    </section>

                    <section id="menores">
                        <h2>Datos de menores de edad</h2>
                        <p>
                            Zooki no está dirigido a menores de edad y no solicita deliberadamente sus datos. El
                            registro de cuentas está previsto para personas mayores de edad. Si se detecta que se
                            ha registrado un menor sin la autorización de su representante legal, sus datos serán
                            suprimidos.
                        </p>
                    </section>

                    <section id="vigencia">
                        <h2>Vigencia</h2>
                        <p>
                            Esta política rige desde el <strong><?php echo $lp_vigencia; ?></strong>. Las bases de
                            datos se conservarán mientras la cuenta permanezca activa y durante el tiempo adicional
                            que exijan las obligaciones legales aplicables, en particular las relativas a la
                            conservación de la historia clínica veterinaria.
                        </p>
                        <p>
                            Cualquier modificación sustancial será comunicada a los titulares a través de los
                            canales de contacto registrados, antes de su entrada en vigencia.
                        </p>
                        <div class="lp-legal__note">
                            <i class="ri-draft-line" aria-hidden="true"></i>
                            <p>
                                Este documento se encuentra pendiente de revisión jurídica y del registro de las
                                bases de datos ante el Registro Nacional de Bases de Datos de la Superintendencia
                                de Industria y Comercio, cuando ello resulte exigible al Responsable.
                            </p>
                        </div>
                    </section>

                </article>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../landing/partials/footer.php'; ?>

    <script src="js/landing.js?v=<?php echo $lp_version; ?>"></script>
</body>
</html>
