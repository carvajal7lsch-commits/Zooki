<?php
$estadoLabel = ['pendiente'=>'Pendiente','confirmada'=>'Confirmada','en_curso'=>'En curso','completada'=>'Completada','cancelada'=>'Cancelada'];
$estadoColor = ['pendiente'=>'#F59E0B','confirmada'=>'#10B981','en_curso'=>'#0C66E4','completada'=>'#15803D','cancelada'=>'#EF4444'];
$estadoActual = strtolower($cita['estado'] ?? 'pendiente');
$foto = !empty($mascota['url_foto']) ? $mascota['url_foto'] : 'https://ui-avatars.com/api/?name='.urlencode($mascota['nombre'] ?? 'M').'&background=e0e0e0&color=555';
function edad($fn){ if(empty($fn)) return 'Desconocida'; $d=(new DateTime())->diff(new DateTime($fn)); if($d->y) return $d->y.' año'.($d->y>1?'s':''); if($d->m) return $d->m.' mes'.($d->m>1?'es':''); return 'Recién nacido'; }
?>
<link rel="stylesheet" href="css/medical-module.css">
<div class="animate__animated animate__fadeIn atencion-wrapper">

<div class="atencion-header">
  <div class="atencion-header-left">
    <a href="index.php?action=vet_agenda" class="btn-volver-atencion"><i class="fas fa-arrow-left"></i> Volver</a>
    <h2><i class="fas fa-stethoscope"></i> Atención Médica</h2>
    <p><strong>Cita #<?= htmlspecialchars($cita['id_cita']??'') ?></strong> &middot; <?= date('d/m/Y',strtotime($cita['fecha'])) ?> <?= substr($cita['hora'],0,5) ?> &middot;
      <span class="estado-badge" style="color:<?=$estadoColor[$estadoActual]?>;background:<?=$estadoColor[$estadoActual]?>15;border:1px solid <?=$estadoColor[$estadoActual]?>40;"><?=$estadoLabel[$estadoActual]??$estadoActual?></span></p>
  </div>
  <?php if($estadoActual==='en_curso'): ?>
  <button class="btn-completar-cita" onclick="completarCitaAtencion(<?=$cita['id_cita']?>)"><i class="fas fa-check-circle"></i> Completar cita</button>
  <?php endif; ?>
</div>

<div class="paciente-ficha">
  <div class="paciente-foto"><img src="<?=$foto?>" alt="<?=htmlspecialchars($mascota['nombre']??'')?>"></div>
  <div class="paciente-datos">
    <h3><?=htmlspecialchars($mascota['nombre']??'Sin nombre')?></h3>
    <div class="paciente-meta">
      <span><i class="fas fa-paw"></i> <?=htmlspecialchars($mascota['nombre_especie']??'')?>/<?=htmlspecialchars($mascota['nombre_raza']??'')?></span>
      <span><i class="fas fa-birthday-cake"></i> <?=edad($mascota['fecha_nacimiento']??'')?></span>
      <span><i class="fas fa-venus-mars"></i> <?=htmlspecialchars($mascota['sexo']??'')?></span>
      <span><i class="fas fa-weight"></i> <?=htmlspecialchars($mascota['peso']??'—')?> kg</span>
    </div>
    <div class="paciente-propietario">
      <i class="fas fa-user"></i> <strong>Propietario:</strong> <?=htmlspecialchars($propietario['nombre_completo']??'—')?>
      <?php if(!empty($propietario['telefono'])): ?>&middot; <i class="fas fa-phone"></i> <?=htmlspecialchars($propietario['telefono'])?><?php endif; ?>
    </div>
  </div>
  <div class="paciente-motivo">
    <label><i class="fas fa-comment-medical"></i> Motivo de la cita</label>
    <p><?=nl2br(htmlspecialchars($cita['motivo']??'Sin motivo'))?></p>
  </div>
</div>

<div class="atencion-tabs">
  <button class="atencion-tab-btn active" onclick="switchAtencionTab(this,'tabAtencion')"><i class="fas fa-stethoscope"></i> Atención Actual</button>
  <button class="atencion-tab-btn" onclick="switchAtencionTab(this,'tabHistorial')"><i class="fas fa-file-medical-alt"></i> Historial (<?=count($consultas)?>)</button>
  <button class="atencion-tab-btn" onclick="switchAtencionTab(this,'tabVacunas')"><i class="fas fa-syringe"></i> Vacunas (<?=count($vacunas)?>)</button>
  <button class="atencion-tab-btn" onclick="switchAtencionTab(this,'tabDesparasitaciones')"><i class="fas fa-shield-alt"></i> Desparasitaciones (<?=count($desparasitaciones)?>)</button>
</div>

<!-- TAB ATENCION -->
<div id="tabAtencion" class="atencion-tab-content active">
  <div class="atencion-cards">
    <div class="atencion-card">
      <div class="atencion-card-header"><i class="fas fa-file-medical-alt"></i><h4>Registrar Consulta</h4></div>
      <form id="formConsultaAtencion" onsubmit="guardarConsultaAtencion(event)">
        <input type="hidden" name="id_mascota" value="<?=$mascota['id_mascota']?>">
        <input type="hidden" name="id_cita" value="<?=$cita['id_cita']?>">
        <div class="form-row"><div class="form-group full"><label>Motivo *</label><input type="text" name="motivo" required value="<?=htmlspecialchars($cita['motivo']??'')?>"></div></div>
        <div class="form-row"><div class="form-group full"><label>Anamnesis</label><textarea name="anamnesis" rows="2"></textarea></div></div>
        <div class="form-row vitals-row">
          <div class="form-group"><label>Peso (kg)</label><input type="number" step="0.01" name="peso" value="<?=htmlspecialchars($mascota['peso']??'')?>"></div>
          <div class="form-group"><label>Temperatura (°C)</label><input type="number" step="0.1" name="temperatura"></div>
          <div class="form-group"><label>F.C. (lpm)</label><input type="number" name="frecuencia_cardiaca"></div>
          <div class="form-group"><label>F.R. (rpm)</label><input type="number" name="frecuencia_respiratoria"></div>
        </div>
        <div class="form-row"><div class="form-group full"><label>Diagnóstico *</label><textarea name="diagnostico" rows="2" required></textarea></div></div>
        <div class="form-row"><div class="form-group full"><label>Tratamiento / Plan</label><textarea name="tratamiento" rows="2"></textarea></div></div>
        <div class="form-row"><div class="form-group full"><label>Observaciones</label><textarea name="observaciones" rows="2"></textarea></div></div>
        <button type="submit" class="btn-submit-atencion"><i class="fas fa-save"></i> Guardar Consulta</button>
      </form>
    </div>

    <div class="atencion-card">
      <div class="atencion-card-header"><i class="fas fa-syringe"></i><h4>Registrar Vacuna</h4></div>
      <form id="formVacunaAtencion" onsubmit="guardarVacunaAtencion(event)">
        <input type="hidden" name="id_mascota" value="<?=$mascota['id_mascota']?>">
        <div class="form-row"><div class="form-group full"><label>Nombre vacuna *</label><input type="text" name="nombre_vacuna" required></div></div>
        <div class="form-row">
          <div class="form-group"><label>Laboratorio</label><input type="text" name="laboratorio"></div>
          <div class="form-group"><label>Lote</label><input type="text" name="lote"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Fecha aplicación *</label><input type="date" name="fecha_aplicacion" required value="<?=date('Y-m-d')?>"></div>
          <div class="form-group"><label>Próxima dosis</label><input type="date" name="fecha_proxima"></div>
        </div>
        <div class="form-row"><div class="form-group full"><label>Observaciones</label><textarea name="observaciones" rows="2"></textarea></div></div>
        <button type="submit" class="btn-submit-atencion vacuna"><i class="fas fa-syringe"></i> Guardar Vacuna</button>
      </form>
    </div>

    <div class="atencion-card">
      <div class="atencion-card-header"><i class="fas fa-shield-alt"></i><h4>Registrar Desparasitación</h4></div>
      <form id="formDesparasitacionAtencion" onsubmit="guardarDesparasitacionAtencion(event)">
        <input type="hidden" name="id_mascota" value="<?=$mascota['id_mascota']?>">
        <div class="form-row">
          <div class="form-group"><label>Tipo</label><select name="tipo"><option>Interna</option><option>Externa</option><option>Completa</option></select></div>
          <div class="form-group"><label>Producto *</label><input type="text" name="producto" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Fecha aplicación *</label><input type="date" name="fecha_aplicacion" required value="<?=date('Y-m-d')?>"></div>
          <div class="form-group"><label>Próxima aplicación</label><input type="date" name="fecha_proxima"></div>
        </div>
        <div class="form-row"><div class="form-group full"><label>Observaciones</label><textarea name="observaciones" rows="2"></textarea></div></div>
        <button type="submit" class="btn-submit-atencion desparasitacion"><i class="fas fa-shield-alt"></i> Guardar Desparasitación</button>
      </form>
    </div>
  </div>
</div>

<!-- TAB HISTORIAL -->
<div id="tabHistorial" class="atencion-tab-content">
  <?php if(empty($consultas)): ?>
    <div class="empty-state"><i class="fas fa-file-medical-alt"></i><p>No hay consultas previas.</p></div>
  <?php else: ?>
    <div class="historial-list">
      <?php foreach($consultas as $con): ?>
        <div class="historial-item">
          <div class="historial-item-header">
            <span class="historial-fecha"><?=date('d/m/Y',strtotime($con['fecha_consulta']))?></span>
            <span class="historial-vet"><i class="fas fa-user-md"></i> <?=htmlspecialchars($con['veterinario']??'')?></span>
          </div>
          <h5><?=htmlspecialchars($con['motivo']??'')?></h5>
          <p><strong>Diagnóstico:</strong> <?=nl2br(htmlspecialchars($con['diagnostico']??''))?></p>
          <?php if(!empty($con['tratamiento'])): ?><p><strong>Tratamiento:</strong> <?=nl2br(htmlspecialchars($con['tratamiento']))?></p><?php endif; ?>
          <?php if(!empty($con['peso'])||!empty($con['temperatura'])): ?>
            <div class="historial-vitals">
              <?php if(!empty($con['peso'])): ?><span><i class="fas fa-weight"></i> <?=$con['peso']?> kg</span><?php endif; ?>
              <?php if(!empty($con['temperatura'])): ?><span><i class="fas fa-thermometer-half"></i> <?=$con['temperatura']?> °C</span><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- TAB VACUNAS -->
<div id="tabVacunas" class="atencion-tab-content">
  <?php if(empty($vacunas)): ?>
    <div class="empty-state"><i class="fas fa-syringe"></i><p>No hay vacunas registradas.</p></div>
  <?php else: ?>
    <table class="data-table atencion-table"><thead><tr><th>Vacuna</th><th>Laboratorio</th><th>Lote</th><th>Aplicación</th><th>Próxima</th></tr></thead><tbody>
      <?php foreach($vacunas as $v): ?>
        <tr><td><?=htmlspecialchars($v['nombre_vacuna']??'')?></td><td><?=htmlspecialchars($v['laboratorio']??'')?></td><td><?=htmlspecialchars($v['lote']??'')?></td>
        <td><?=!empty($v['fecha_aplicacion'])?date('d/m/Y',strtotime($v['fecha_aplicacion'])):'—'?></td>
        <td><?=!empty($v['fecha_proxima_dosis'])?date('d/m/Y',strtotime($v['fecha_proxima_dosis'])):'—'?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

<!-- TAB DESPARASITACIONES -->
<div id="tabDesparasitaciones" class="atencion-tab-content">
  <?php if(empty($desparasitaciones)): ?>
    <div class="empty-state"><i class="fas fa-shield-alt"></i><p>No hay desparasitaciones registradas.</p></div>
  <?php else: ?>
    <table class="data-table atencion-table"><thead><tr><th>Tipo</th><th>Producto</th><th>Aplicación</th><th>Próxima</th><th>Obs.</th></tr></thead><tbody>
      <?php foreach($desparasitaciones as $d): ?>
        <tr><td><?=htmlspecialchars($d['tipo']??'')?></td><td><?=htmlspecialchars($d['producto']??'')?></td>
        <td><?=!empty($d['fecha_aplicacion'])?date('d/m/Y',strtotime($d['fecha_aplicacion'])):'—'?></td>
        <td><?=!empty($d['fecha_proxima'])?date('d/m/Y',strtotime($d['fecha_proxima'])):'—'?></td>
        <td><?=htmlspecialchars($d['observaciones']??'')?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

</div>

<!-- CSS -->
<style>
.atencion-wrapper{padding:1rem 1.5rem}
.atencion-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid #E2E8F0}
.atencion-header-left h2{margin:0 0 .35rem;font-size:1.4rem;color:#1A1D23}
.atencion-header-left p{margin:0;color:#64748B;font-size:.9rem}
.btn-volver-atencion{display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;color:#64748B;text-decoration:none;margin-bottom:.5rem}
.btn-volver-atencion:hover{color:#0C66E4}
.estado-badge{font-size:.75rem;font-weight:600;padding:.15rem .6rem;border-radius:12px}
.btn-completar-cita{background:#15803D;color:white;border:none;padding:.65rem 1.2rem;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem}
.btn-completar-cita:hover{background:#166534}

.paciente-ficha{display:flex;gap:1.25rem;background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.paciente-foto img{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #E2E8F0}
.paciente-datos{flex:1;min-width:0}
.paciente-datos h3{margin:0 0 .5rem;font-size:1.25rem;color:#1A1D23}
.paciente-meta{display:flex;flex-wrap:wrap;gap:.6rem 1.2rem;margin-bottom:.5rem}
.paciente-meta span{font-size:.82rem;color:#475569;display:inline-flex;align-items:center;gap:.35rem}
.paciente-meta i{color:#94A3B8;font-size:.8rem}
.paciente-propietario{font-size:.85rem;color:#64748B}
.paciente-propietario i{color:#0C66E4}
.paciente-motivo{min-width:220px;max-width:320px;border-left:1px solid #E2E8F0;padding-left:1.25rem}
.paciente-motivo label{display:block;font-size:.7rem;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem}
.paciente-motivo p{margin:0;font-size:.9rem;color:#334155;line-height:1.4}

.atencion-tabs{display:flex;gap:.25rem;border-bottom:2px solid #E2E8F0;margin-bottom:1.25rem}
.atencion-tab-btn{background:none;border:none;padding:.75rem 1.25rem;font-size:.85rem;font-weight:600;color:#64748B;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;display:inline-flex;align-items:center;gap:.4rem;transition:all .2s;font-family:inherit}
.atencion-tab-btn:hover{color:#0C66E4;background:#F1F5F9;border-radius:6px 6px 0 0}
.atencion-tab-btn.active{color:#0C66E4;border-bottom-color:#0C66E4;background:#F8FAFC;border-radius:6px 6px 0 0}
.atencion-tab-content{display:none}
.atencion-tab-content.active{display:block;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}

.atencion-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem}
.atencion-card{background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:1.25rem}
.atencion-card-header{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1px solid #F1F5F9}
.atencion-card-header i{font-size:1.1rem;color:#0C66E4}
.atencion-card-header h4{margin:0;font-size:1rem;color:#1A1D23}

.form-row{display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap}
.form-group{flex:1;min-width:120px}
.form-group.full{flex:1 1 100%}
.form-group label{display:block;font-size:.72rem;font-weight:600;color:#64748B;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.3px}
.form-group input,.form-group textarea,.form-group select{width:100%;box-sizing:border-box;padding:.55rem .75rem;border:1.5px solid #E2E8F0;border-radius:8px;font-family:inherit;font-size:.875rem;color:#1A1D23;background:#F8FAFC;transition:border-color .2s}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:#0C66E4;background:#fff}
.vitals-row .form-group{min-width:90px}
.btn-submit-atencion{width:100%;padding:.7rem;background:#0C66E4;color:white;border:none;border-radius:8px;font-family:inherit;font-size:.9rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;transition:background .2s;margin-top:.5rem}
.btn-submit-atencion:hover{background:#0747A6}
.btn-submit-atencion.vacuna{background:#10B981}
.btn-submit-atencion.vacuna:hover{background:#059669}
.btn-submit-atencion.desparasitacion{background:#8B5CF6}
.btn-submit-atencion.desparasitacion:hover{background:#7C3AED}

.historial-list{display:flex;flex-direction:column;gap:1rem}
.historial-item{background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:1rem 1.25rem}
.historial-item-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem}
.historial-fecha{font-size:.8rem;font-weight:600;color:#0C66E4;background:#E9F2FF;padding:.15rem .5rem;border-radius:12px}
.historial-vet{font-size:.8rem;color:#64748B}
.historial-item h5{margin:0 0 .5rem;font-size:1rem;color:#1A1D23}
.historial-item p{margin:0 0 .4rem;font-size:.85rem;color:#475569;line-height:1.45}
.historial-vitals{display:flex;gap:1rem;margin-top:.5rem;padding-top:.5rem;border-top:1px solid #F1F5F9}
.historial-vitals span{font-size:.8rem;color:#64748B}

.atencion-table{width:100%;margin-top:0}
.atencion-table th{background:#F8FAFC;font-size:.75rem;text-transform:uppercase;letter-spacing:.3px}
.atencion-table td{font-size:.85rem}
.empty-state{text-align:center;padding:3rem 1rem;color:#94A3B8}
.empty-state i{font-size:2.5rem;margin-bottom:1rem;display:block}
.empty-state p{margin:0;font-size:.9rem}
</style>

<script>
function switchAtencionTab(btn,tabId){
  document.querySelectorAll('.atencion-tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.atencion-tab-content').forEach(c=>c.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}
async function guardarConsultaAtencion(e){
  e.preventDefault();
  const fd=new FormData(e.target);
  try{
    const res=await fetch('index.php?action=registrar_consulta_ajax',{method:'POST',body:fd});
    const r=await res.json();
    if(r.success){Swal.fire({title:'Consulta guardada',text:r.message,icon:'success',confirmButtonColor:'#0C66E4'});e.target.reset();setTimeout(()=>location.reload(),800);}
    else Swal.fire({title:'Error',text:r.message,icon:'error',confirmButtonColor:'#0C66E4'});
  }catch(err){console.error(err);Swal.fire({title:'Error',text:'No se pudo guardar.',icon:'error',confirmButtonColor:'#0C66E4'});}
}
async function guardarVacunaAtencion(e){
  e.preventDefault();
  const fd=new FormData(e.target);
  try{
    const res=await fetch('index.php?action=registrar_vacuna_ajax',{method:'POST',body:fd});
    const r=await res.json();
    if(r.success){Swal.fire({title:'Vacuna registrada',text:r.message,icon:'success',confirmButtonColor:'#10B981'});e.target.reset();setTimeout(()=>location.reload(),800);}
    else Swal.fire({title:'Error',text:r.message,icon:'error',confirmButtonColor:'#0C66E4'});
  }catch(err){console.error(err);Swal.fire({title:'Error',text:'No se pudo registrar.',icon:'error',confirmButtonColor:'#0C66E4'});}
}
async function guardarDesparasitacionAtencion(e){
  e.preventDefault();
  const fd=new FormData(e.target);
  try{
    const res=await fetch('index.php?action=registrar_desparasitacion_ajax',{method:'POST',body:fd});
    const r=await res.json();
    if(r.success){Swal.fire({title:'Desparasitación registrada',text:r.message,icon:'success',confirmButtonColor:'#8B5CF6'});e.target.reset();setTimeout(()=>location.reload(),800);}
    else Swal.fire({title:'Error',text:r.message,icon:'error',confirmButtonColor:'#0C66E4'});
  }catch(err){console.error(err);Swal.fire({title:'Error',text:'No se pudo registrar.',icon:'error',confirmButtonColor:'#0C66E4'});}
}
async function completarCitaAtencion(idCita){
  const form=new URLSearchParams({id_cita:idCita});
  try{
    const res=await fetch('index.php?action=completar_cita_ajax',{method:'POST',body:form});
    const r=await res.json();
    if(r.success){Swal.fire({title:'Cita completada',text:r.message,icon:'success',confirmButtonColor:'#15803D'}).then(()=>location.reload());}
    else Swal.fire({title:'Error',text:r.message,icon:'error',confirmButtonColor:'#0C66E4'});
  }catch(err){console.error(err);Swal.fire({title:'Error',text:'No se pudo completar.',icon:'error',confirmButtonColor:'#0C66E4'});}
}
</script>
