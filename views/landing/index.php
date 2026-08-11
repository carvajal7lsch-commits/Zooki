<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Sistema de Gestión Veterinaria Inteligente</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/landing.css?v=<?php echo time(); ?>">
</head>
<body class="landing-body">
    <!-- Navbar -->
    <header class="landing-header">
        <div class="landing-nav-container">
            <div class="landing-logo">
                <img src="img/icon_blue.png" alt="Zooki Logo" class="nav-logo-img">
                <span class="nav-logo-text">Zooki</span>
            </div>
            
            <nav class="landing-navbar">
                <a href="#inicio" class="nav-link">Inicio</a>
                <a href="#caracteristicas" class="nav-link">Características</a>
                <a href="#nosotros" class="nav-link">Nosotros</a>
                <a href="docs/index.php" class="nav-link nav-doc-link" target="_blank">
                    <i class="ri-file-text-line"></i> Documentación
                </a>
            </nav>
            
            <div class="landing-actions">
                <?php if (isset($_SESSION['usuario_doc'])): ?>
                    <a href="index.php?action=dashboard" class="btn btn-primary btn-nav-cta">Ir al Panel</a>
                <?php else: ?>
                    <a href="index.php?action=login" class="btn btn-primary btn-nav-cta">Iniciar Sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="inicio" class="hero-section">
        <div class="hero-container">
            <div class="hero-content animate__animated animate__fadeInLeft">
                <span class="hero-badge">Veterinaria del Futuro, Hoy</span>
                <h1 class="hero-title">Sistema de Gestión Veterinaria Inteligente</h1>
                <p class="hero-subtitle">
                    Zooki optimiza la administración de tu clínica, cuida de tus pacientes y mantiene conectados a los propietarios de manera sencilla y eficiente.
                </p>
                <div class="hero-ctas">
                    <?php if (isset($_SESSION['usuario_doc'])): ?>
                        <a href="index.php?action=dashboard" class="btn btn-hero-primary">
                            Ir al Panel de Control <i class="ri-arrow-right-line"></i>
                        </a>
                    <?php else: ?>
                        <a href="index.php?action=login" class="btn btn-hero-primary">
                            Acceder al Sistema <i class="ri-arrow-right-line"></i>
                        </a>
                    <?php endif; ?>
                    <a href="docs/index.php" class="btn btn-hero-secondary" target="_blank">
                        Leer Documentación <i class="ri-book-open-line"></i>
                    </a>
                </div>
            </div>
            <div class="hero-image-area animate__animated animate__fadeInRight">
                <div class="hero-image-wrapper">
                    <img src="img/pets.png" alt="Zooki Mascotas" class="hero-img">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="caracteristicas" class="features-section">
        <div class="section-header">
            <h2 class="section-title">Soluciones diseñadas para tu veterinaria</h2>
            <p class="section-desc">Todo lo necesario para elevar la calidad del servicio clínico y administrativo.</p>
        </div>
        
        <div class="features-grid">
            <!-- Feature Card 1 -->
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="ri-heart-pulse-line"></i>
                </div>
                <h3>Historial Clínico Inteligente</h3>
                <p>Registra recetas, diagnósticos, planes de tratamiento y vacunas en un expediente médico digital seguro y accesible.</p>
            </div>
            <!-- Feature Card 2 -->
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="ri-calendar-todo-line"></i>
                </div>
                <h3>Agenda Interactiva</h3>
                <p>Evita el solapamiento de horarios. Administra las citas médicas y de control de los veterinarios de forma inteligente.</p>
            </div>
            <!-- Feature Card 3 -->
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="ri-user-settings-line"></i>
                </div>
                <h3>Portal Auto-Gestionable</h3>
                <p>Los propietarios pueden registrar a sus mascotas, programar citas y descargar su ficha de salud oficial en PDF.</p>
            </div>
            <!-- Feature Card 4 -->
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="ri-shield-check-line"></i>
                </div>
                <h3>Seguridad Avanzada</h3>
                <p>Autenticación robusta integrada con Google Identity Services, protección de sesiones contra ataques y auditoría integrada.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="nosotros" class="landing-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="img/icon_white.png" alt="Zooki Logo Blanco" class="footer-logo-img">
                    <span class="footer-logo-text">Zooki</span>
                </div>
                <p class="footer-tagline">El cuidado inteligente para quienes más nos importan.</p>
            </div>
            <div class="footer-links">
                <h4>Enlaces Rápidos</h4>
                <a href="#inicio">Inicio</a>
                <a href="#caracteristicas">Características</a>
                <a href="docs/index.php" target="_blank">Documentación Técnica</a>
                <a href="index.php?action=login">Portal de Acceso</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Zooki. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>
