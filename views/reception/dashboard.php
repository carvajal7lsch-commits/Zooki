<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"] ?? 'Recepcionista'))[0];
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_CO.UTF-8', 'spanish');
$fecha_banner = mb_strtoupper(strftime("%A, %d DE %B %Y"));
?>

<!-- ══ WELCOME BAR + KPIs inline ══════════════════════════════ -->
<div class="hero-banner animate__animated animate__fadeInDown">
    <div class="hero-content">
        <div class="hero-date"><?= $fecha_banner ?></div>
        <h1 class="hero-title">Resumen del Panel</h1>
        <div class="hero-stats">
            <span class="hero-greeting">Bienvenido, <?= htmlspecialchars($nombre) ?></span>
            <span style="opacity: 0.5; font-size: 0.8rem;">•</span>
            <div class="hero-badges">
                <span class="hero-badge"><i class="fas fa-paw"></i> Panel de Recepción</span>
                <span class="hero-badge"><i class="far fa-calendar-check"></i> Gestión Rápida</span>
            </div>
        </div>
    </div>
    <div class="hero-image">
        <img src="img/pets.png" alt="Mascotas" onerror="this.src='img/hero-puppy.png';">
    </div>
</div>

<div class="section-card animate__animated animate__fadeInUp">
    <div class="section-header">
        <h2>
            <i class="fas fa-bolt" style="color:#F59E0B;margin-right:.5rem;"></i>
            Accesos Rápidos
        </h2>
    </div>
    <div class="quick-actions-grid" style="display: flex; gap: 1rem;">
        <a href="index.php?action=reception_nueva_cita" class="btn-primary" style="text-decoration: none; padding: 1rem 2rem; border-radius: 12px;"><i class="fas fa-plus"></i> Nueva Cita</a>
        <a href="index.php?action=reception_agenda" class="btn-secondary" style="text-decoration: none; padding: 1rem 2rem; border-radius: 12px;"><i class="fas fa-calendar-alt"></i> Agenda</a>
        <a href="index.php?action=reception_pacientes" class="btn-secondary" style="text-decoration: none; padding: 1rem 2rem; border-radius: 12px;"><i class="fas fa-paw"></i> Pacientes</a>
    </div>
</div>
