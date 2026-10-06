# Retoque final de los .docx con Microsoft Word (specs/exportar-documentos.md).
# Lo llama scripts/docs/word.mjs. Hace lo que Pandoc no puede expresar:
#  - EX-4: llena el índice (campo TOC) con los números de página reales.
#  - EX-6: repite el encabezado de cada tabla en cada hoja, no parte filas
#    entre hojas y mantiene enteras las tablas cortas.
# Si Word no está instalado no falla: los archivos sirven igual y el índice
# se llena al actualizar campos al abrirlos.
# Los mensajes van sin tildes: PowerShell 5.1 lee este archivo como ANSI.

param([Parameter(Mandatory = $true)][string]$Lista)

# Una tabla de hasta tantas filas se mantiene entera en una hoja.
$FilasTablaCorta = 20
# Igual que MAX_CORTA en plantilla/impresion.js.
$MaxColumnaCorta = 14

$archivos = Get-Content -LiteralPath $Lista -Encoding UTF8 | Where-Object { $_ }

try {
    $word = New-Object -ComObject Word.Application
} catch {
    Write-Host '  Word no esta instalado: el indice se llena al abrir cada archivo.'
    exit 0
}

$word.Visible = $false
$word.DisplayAlerts = 0

try {
    foreach ($ruta in $archivos) {
        $doc = $word.Documents.Open($ruta, $false, $false, $false)
        foreach ($tabla in $doc.Tables) {
            # En columnas de valores cortos (RF-0.1, Alta) el guion pasa a
            # ser de no separacion (^~): si no, Word parte «RF-» / «0.1».
            foreach ($columna in $tabla.Columns) {
                $largo = 0
                for ($f = 2; $f -le $columna.Cells.Count; $f++) {
                    $texto = $columna.Cells.Item($f).Range.Text.TrimEnd([char]13, [char]7)
                    if ($texto.Length -gt $largo) { $largo = $texto.Length }
                }
                if ($largo -le $MaxColumnaCorta) {
                    for ($f = 2; $f -le $columna.Cells.Count; $f++) {
                        $rango = $columna.Cells.Item($f).Range
                        [void]$rango.Find.Execute('-', $false, $false, $false, $false, $false, $true, 0, $false, '^~', 2)
                    }
                }
            }

            # Ancho de columnas segun su contenido y luego estirado al ancho
            # de la hoja, como hace el navegador en el PDF: asi un ID corto
            # (RF-0.1) no se parte en dos renglones.
            $tabla.AllowAutoFit = $true
            $tabla.AutoFitBehavior(1)
            $tabla.AutoFitBehavior(2)
            $tabla.Rows.AllowBreakAcrossPages = 0
            $tabla.Rows.Item(1).HeadingFormat = -1
            $filas = $tabla.Rows.Count
            if ($filas -le $FilasTablaCorta) {
                # «Conservar con el siguiente» en todas las filas menos la
                # última: Word no puede partir la tabla y la pasa entera.
                for ($i = 1; $i -lt $filas; $i++) {
                    $tabla.Rows.Item($i).Range.ParagraphFormat.KeepWithNext = -1
                }
            }
        }
        $doc.Repaginate()
        foreach ($indice in $doc.TablesOfContents) { $indice.Update() }
        $doc.Save()
        $doc.Close()
        Write-Host ('  Word: ' + [System.IO.Path]::GetFileName($ruta))
    }
} finally {
    $word.Quit()
    [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word)
}
