<?php
/** D-1, A.4.2: valores v1 aprobados; los identificadores se reasignan por clínica. */
final class ValoresInicialesClinica
{
    public const HORARIOS = [
        [1, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'],
        [2, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'],
        [3, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'],
        [4, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'],
        [5, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'],
        [6, 0, 1, 0, '08:00:00', '12:00:00', null, null],
        [7, 0, 0, 0, null, null, null, null]
    ];
    public const TIPOS = [
        [1, 'Vacunación/Revisión rápida', 15, 'Vacunación rutinaria o revisión rápida de la mascota', '#10B981', 1],
        [2, 'Consulta general/Enfermedad', 30, 'Consulta general por enfermedad o chequeo completo', '#0C66E4', 1],
        [3, 'Primera visita/Mascota nueva', 45, 'Primera visita para mascota nueva, requiere historia clínica completa', '#8B5CF6', 1],
        [4, 'Consulta de especialidad/Urgencia', 60, 'Consulta de especialidad o urgencia veterinaria', '#EF4444', 1],
        [5, 'Desparasitación', 15, 'Aplicación de desparasitante interna o externa', '#F59E0B', 1],
        [6, 'Control post-operatorio', 20, 'Control después de cirugía o procedimiento', '#6366F1', 1]
    ];
    public const VACUNAS = [
        [1, 'Pentavalente', 'Protege contra Distemper, Hepatitis, Parvovirus, Parainfluenza y Leptospirosis', 1],
        [2, 'Séxtuple', 'Pentavalente más Coronavirus', 1],
        [3, 'Rabia', 'Vacuna antirrábica obligatoria', 1],
        [4, 'Parvovirus', 'Protege contra Parvovirus canino', 1],
        [5, 'Distemper', 'Protege contra Moquillo canino', 1],
        [6, 'Hepatitis Infecciosa', 'Protege contra Hepatitis infecciosa canina', 1],
        [7, 'Leptospirosis', 'Protege contra Leptospirosis', 1],
        [8, 'Parainfluenza', 'Protege contra Parainfluenza canina', 1],
        [9, 'Coronavirus', 'Protege contra Coronavirus canino', 1],
        [10, 'Triple Felina', 'Protege contra Panleucopenia, Rinotraqueítis y Calicivirus', 1],
        [11, 'Leucemia Felina', 'Protege contra Leucemia viral felina', 1],
        [12, 'Clamidiosis', 'Protege contra Clamidiosis felina', 1],
        [13, 'Bordetella', 'Protege contra Tos de las perreras', 1],
        [14, 'Lyme', 'Protege contra Enfermedad de Lyme', 1],
        [15, 'Otra', 'Otra vacuna no listada', 1]
    ];
    public const ESPECIES_VACUNAS = [
        [1, 1, 1],
        [2, 1, 2],
        [3, 1, 3],
        [4, 1, 4],
        [5, 1, 5],
        [6, 1, 6],
        [7, 1, 7],
        [8, 1, 8],
        [9, 1, 9],
        [10, 1, 13],
        [11, 1, 14],
        [12, 1, 15],
        [16, 2, 3],
        [13, 2, 10],
        [14, 2, 11],
        [15, 2, 12],
        [17, 2, 15],
        [18, 3, 15],
        [19, 4, 15],
        [20, 5, 15],
        [21, 6, 15]
    ];
    public const LABORATORIOS = [
        [1, 'MSD Animal Health', 1],
        [2, 'Zoetis', 1],
        [3, 'Boehringer Ingelheim', 1],
        [4, 'Elanco', 1],
        [5, 'Ceva', 1],
        [6, 'Virbac', 1],
        [7, 'Merial', 1],
        [8, 'Bayer', 1],
        [9, 'Laboratorios Calier', 1],
        [10, 'Laboratorios Syntex', 1],
        [11, 'Laboratorios Farvet', 1]
    ];
    public const PRODUCTOS = [
        [1, 'Ivermectina', 'interna', 1],
        [2, 'Fenbendazol', 'interna', 1],
        [3, 'Praziquantel', 'interna', 1],
        [4, 'Pyrantel', 'interna', 1],
        [5, 'Milbemycina', 'interna', 1],
        [6, 'Selamectina', 'externa', 1],
        [7, 'Fipronil', 'externa', 1],
        [8, 'Imidacloprid', 'externa', 1],
        [9, 'Permetrina', 'externa', 1],
        [10, 'Deltametrina', 'externa', 1],
        [11, 'Afoxolaner', 'ambas', 1],
        [12, 'Fluralaner', 'ambas', 1],
        [13, 'Sarolaner', 'ambas', 1],
        [14, 'Lufenuron', 'interna', 1],
        [15, 'Nitenpyram', 'externa', 1]
    ];
}
