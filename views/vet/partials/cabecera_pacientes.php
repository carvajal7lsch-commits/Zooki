<div class="pacientes-cabecera directorio-cabecera" data-pacientes-directorio>
    <div class="head-title-desc">
        <h1 class="users-page-title"><i class="fas fa-paw icon-primary" aria-hidden="true"></i> Pacientes</h1>
        <p class="users-module-desc">Directorio de clientes y sus mascotas</p>
    </div>
    <div class="head-top-right">
        <div class="tabs-wrapper">
            <button type="button" data-c3-click="evento0" id="tabOwners" class="tab-btn active">
                <i class="fas fa-users" aria-hidden="true"></i><span>Propietarios</span>
            </button>
            <button type="button" data-c3-click="evento1" id="tabPets" class="tab-btn">
                <i class="fas fa-paw" aria-hidden="true"></i><span>Mascotas</span>
            </button>
        </div>
    </div>
</div>
<div class="pacientes-cabecera directorio-cabecera pacientes-cabecera--detalle" data-pacientes-detalle hidden>
    <button type="button" class="btn-create btn-create-outline propietario-volver" data-c3-click="evento17">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5m7-7-7 7 7 7"/></svg><span id="dossierVolverTexto">Propietarios</span>
    </button>
    <div class="propietario-ruta">
        <p>Pacientes <span aria-hidden="true">›</span> <span id="dossierOwnerRuta"></span><span id="dossierPetRuta" hidden> <span aria-hidden="true">›</span> <strong id="dossierPetRutaNombre"></strong></span></p>
        <h1 id="dossierOwnerTitle"></h1>
    </div>
    <div class="pacientes-edicion-movil" data-pacientes-editar></div>
</div>
