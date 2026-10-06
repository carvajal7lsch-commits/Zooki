-- Filtro de Pandoc para el Word con el formato de la plantilla de documentos
-- del proyecto y los rótulos APA 7 (specs/exportar-documentos.md).
-- Lo usa scripts/docs/word.mjs; los estilos que nombra están en referencia.mjs.
-- Recibe en un JSON (ruta en la variable de entorno ZOOKI_DATOS) los datos de
-- la portada y las figuras que Chrome ya dibujó para el PDF.

local stringify = pandoc.utils.stringify

local function con_estilo(estilo, bloques)
  return pandoc.Div(bloques, { ['custom-style'] = estilo })
end

local function parrafo(estilo, inlines)
  return con_estilo(estilo, { pandoc.Para(inlines) })
end

local function texto(estilo, cadena)
  return parrafo(estilo, { pandoc.Str(cadena) })
end

-- EX-4: campo TOC de Word con los títulos de nivel 1 y 2. Lo llena Word al
-- actualizar campos; word.mjs lo hace con Word si está instalado.
local INDICE = [[
<w:p><w:pPr><w:pStyle w:val="TOC1"/></w:pPr>
<w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>
<w:r><w:instrText xml:space="preserve"> TOC \o "1-2" \h \z \u </w:instrText></w:r>
<w:r><w:fldChar w:fldCharType="separate"/></w:r>
<w:r><w:t>Actualice el índice: clic derecho sobre este texto y «Actualizar campos».</w:t></w:r>
<w:r><w:fldChar w:fldCharType="end"/></w:r>
</w:p>]]

local function escapar(texto)
  return (tostring(texto or ''):gsub('&', '&amp;'):gsub('<', '&lt;'):gsub('>', '&gt;'))
end

-- Celda de la tabla de firmas de la ficha del documento (EX-10).
local function celda_firma(texto, opciones)
  local ancho = 4680
  local sombra = opciones.encabezado and '<w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/>' or ''
  local negrita = opciones.encabezado and '<w:b/><w:bCs/>' or ''
  local alineacion = opciones.encabezado and '<w:jc w:val="center"/>' or ''
  return '<w:tc><w:tcPr><w:tcW w:w="' .. ancho .. '" w:type="dxa"/>' .. sombra .. '</w:tcPr>'
    .. '<w:p><w:pPr><w:pStyle w:val="Celda"/>' .. alineacion .. '</w:pPr>'
    .. '<w:r><w:rPr>' .. negrita .. '</w:rPr><w:t xml:space="preserve">' .. escapar(texto) .. '</w:t></w:r></w:p></w:tc>'
end

local function tabla_firmas(autor, instructor)
  local fila = function(celdas, alto)
    local altura = alto and ('<w:trPr><w:trHeight w:val="' .. alto .. '" w:hRule="atLeast"/></w:trPr>') or ''
    return '<w:tr>' .. altura .. table.concat(celdas) .. '</w:tr>'
  end
  return '<w:tbl><w:tblPr><w:tblStyle w:val="Table"/><w:tblW w:w="9360" w:type="dxa"/><w:tblLayout w:type="fixed"/></w:tblPr>'
    .. '<w:tblGrid><w:gridCol w:w="4680"/><w:gridCol w:w="4680"/></w:tblGrid>'
    .. fila({ celda_firma('Elaborado por', { encabezado = true }), celda_firma('Revisado por', { encabezado = true }) })
    .. fila({ celda_firma('', {}), celda_firma('', {}) }, 1250)
    .. fila({ celda_firma('Fdo.: ' .. autor .. ' — Aprendiz', {}), celda_firma('Fdo.: ' .. instructor .. ' — Instructor', {}) })
    .. '</w:tbl>'
end

-- Tabla de revisiones de la ficha, como Markdown para que reciba el mismo
-- trato que las demás tablas (anchos y celdas compactas).
local function tabla_historial(historial)
  if type(historial) ~= 'table' or type(historial.encabezado) ~= 'table' then return nil end
  local function fila(celdas)
    local partes = {}
    for _, c in ipairs(celdas) do table.insert(partes, (tostring(c):gsub('|', '\\|'))) end
    return '| ' .. table.concat(partes, ' | ') .. ' |'
  end
  local lineas = { fila(historial.encabezado) }
  local guiones = {}
  for _ = 1, #historial.encabezado do table.insert(guiones, '---') end
  table.insert(lineas, '|' .. table.concat(guiones, '|') .. '|')
  for _, f in ipairs(historial.filas or {}) do table.insert(lineas, fila(f)) end
  -- Con salto final: sin él, Pandoc 3.1 pierde la última fila de la tabla.
  local leido = pandoc.read(table.concat(lineas, '\n') .. '\n', 'gfm')
  return leido.blocks[1]
end

-- EX-6: ancho de columna proporcional al texto más largo de la columna, con
-- tope para que una columna de párrafos no aplaste a las demás. Sin esto
-- Pandoc reparte el ancho por igual y la columna «ID» queda tan ancha como
-- la de la descripción.
local function anchos(tabla)
  local largos, palabras = {}, {}
  local function medir(filas)
    for _, fila in ipairs(filas) do
      for i, celda in ipairs(fila.cells) do
        local texto = stringify(celda.contents)
        local n = utf8.len(texto) or 0
        if n > (largos[i] or 0) then largos[i] = n end
        for palabra in texto:gmatch('%S+') do
          local p = math.min(utf8.len(palabra) or 0, 14)
          if p > (palabras[i] or 0) then palabras[i] = p end
        end
      end
    end
  end
  medir(tabla.head.rows)
  for _, cuerpo in ipairs(tabla.bodies) do medir(cuerpo.body) end

  local columnas = #tabla.colspecs
  local pesos, total = {}, 0
  for i = 1, columnas do
    local peso = math.min(math.max(largos[i] or 1, 4), 45) + 2
    pesos[i] = peso
    total = total + peso
  end
  local fracciones = {}
  for i = 1, columnas do fracciones[i] = pesos[i] / total end

  -- Ninguna columna más angosta que su palabra más larga (hasta 14 letras):
  -- con la cuadrícula y su relleno, «RF-0.1» o «Prioridad» se partirían.
  -- Unos 0,19 cm por letra a 9 pt, más 0,4 cm de relleno, sobre 16,5 cm.
  local faltante, holgura = 0, 0
  local minimos = {}
  for i = 1, columnas do
    minimos[i] = ((palabras[i] or 1) * 0.19 + 0.4) / 16.5
    if fracciones[i] < minimos[i] then
      faltante = faltante + (minimos[i] - fracciones[i])
      fracciones[i] = minimos[i]
    else
      holgura = holgura + (fracciones[i] - minimos[i])
    end
  end
  if faltante > 0 and holgura > 0 then
    for i = 1, columnas do
      if fracciones[i] > minimos[i] then
        fracciones[i] = fracciones[i] - (fracciones[i] - minimos[i]) / holgura * faltante
      end
    end
  end
  for i, spec in ipairs(tabla.colspecs) do
    tabla.colspecs[i] = { spec[1], fracciones[i] }
  end
end

-- Las celdas van a interlineado sencillo (APA lo permite en tablas).
local function celdas_compactas(tabla)
  local function recorrer(filas)
    for _, fila in ipairs(filas) do
      for _, celda in ipairs(fila.cells) do
        -- Pandoc le pone «Compact» al texto suelto (Plain) de una celda y no
        -- respeta el custom-style del Div; como párrafo (Para) sí lo toma.
        local bloques = celda.contents:walk({ Plain = function(p) return pandoc.Para(p.content) end })
        celda.contents = { con_estilo('Celda', bloques) }
      end
    end
  end
  recorrer(tabla.head.rows)
  for _, cuerpo in ipairs(tabla.bodies) do recorrer(cuerpo.body) end
end

local function filas_de_datos(tabla)
  local n = 0
  for _, cuerpo in ipairs(tabla.bodies) do n = n + #cuerpo.body end
  return n
end

-- EX-8: un enlace a otro .md no existe dentro del Word; queda como texto.
function Link(enlace)
  if not enlace.target:match('^https?:') and not enlace.target:match('^mailto:') then
    return enlace.content
  end
end

local function leer_datos()
  local ruta = os.getenv('ZOOKI_DATOS')
  local archivo = ruta and io.open(ruta, 'rb')
  if not archivo then return {} end
  local json = archivo:read('a')
  archivo:close()
  return pandoc.json.decode(json, false)
end

function Pandoc(doc)
  local z = leer_datos()
  local function dato(clave)
    local valor = z[clave]
    return type(valor) == 'string' and valor or ''
  end
  local figuras = z.figuras or {}

  local salida = {}
  local function agregar(bloque) table.insert(salida, bloque) end

  local function unir(separador, claves)
    local partes = {}
    for _, clave in ipairs(claves) do
      if dato(clave) ~= '' then table.insert(partes, dato(clave)) end
    end
    return table.concat(partes, separador)
  end

  -- ── Portada (EX-3), como la plantilla: el bloque del título entre líneas
  -- dobles a la derecha, y abajo el logo y el mes ──
  agregar(pandoc.RawBlock('openxml', '<w:p><w:pPr><w:pStyle w:val="PortadaEspacio"/><w:spacing w:before="3800" w:after="0"/></w:pPr></w:p>'))
  agregar(texto('PortadaTitulo', dato('titulo')))
  if dato('proyecto') ~= '' then agregar(texto('PortadaProyecto', 'Proyecto: ' .. dato('proyecto'))) end
  if dato('subtitulo') ~= '' then agregar(texto('PortadaRevision', dato('subtitulo'))) end
  agregar(pandoc.RawBlock('openxml', '<w:p><w:pPr><w:pStyle w:val="PortadaAutoria"/><w:spacing w:before="0" w:after="200"/></w:pPr></w:p>'))
  if dato('autor') ~= '' then agregar(texto('PortadaAutoria', 'Elaborado por: ' .. dato('autor'))) end
  if dato('instructor') ~= '' then agregar(texto('PortadaAutoria', 'Instructor: ' .. dato('instructor'))) end
  for _, linea in ipairs({ unir(' · ', { 'programa', 'curso' }), unir(' · ', { 'institucion', 'centro' }), dato('ciudad') }) do
    if linea ~= '' then agregar(texto('PortadaAutoria', linea)) end
  end
  agregar(pandoc.RawBlock('openxml', '<w:p><w:pPr><w:pStyle w:val="PortadaEspacio"/><w:spacing w:before="2600" w:after="0"/></w:pPr></w:p>'))
  local pie = {}
  if dato('logo') ~= '' then
    table.insert(pie, pandoc.Image({}, dato('logo'), '', { width = '1.1cm' }))
  end
  table.insert(pie, pandoc.RawInline('openxml', '<w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1565C0"/></w:rPr><w:t xml:space="preserve"> Zooki</w:t></w:r><w:r><w:tab/></w:r>'))
  table.insert(pie, pandoc.Str(dato('fecha')))
  agregar(parrafo('PortadaPie', pie))

  -- ── Ficha del documento (EX-10) ──
  agregar(texto('TituloSeccion', 'Ficha del documento'))
  local historial = tabla_historial(z.historial)
  if historial then
    anchos(historial)
    celdas_compactas(historial)
    agregar(historial)
  end
  agregar(texto('FichaTexto', 'Documento validado por las partes en fecha: ____________________'))
  agregar(pandoc.RawBlock('openxml', tabla_firmas(dato('autor'), dato('instructor'))))

  -- ── Contenido (EX-4) ──
  agregar(texto('TituloSeccion', 'Contenido'))
  agregar(pandoc.RawBlock('openxml', INDICE))
  -- El cuerpo empieza en hoja nueva aunque no abra con un título.
  agregar(pandoc.RawBlock('openxml', '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'))

  local ultimo_titulo = dato('titulo')
  local n_tabla, n_figura = 0, 0

  for _, bloque in ipairs(doc.blocks) do
    if bloque.t == 'Header' then
      -- El .md usa ## para el nivel 1 de APA (# es el título del documento).
      bloque.level = math.max(bloque.level - 1, 1)
      ultimo_titulo = stringify(bloque.content)
      agregar(bloque)

    elseif bloque.t == 'Table' then
      anchos(bloque)
      celdas_compactas(bloque)
      -- Igual que en el PDF: una tabla de una sola fila es una ficha y no
      -- se numera (repetiría el título que tiene encima).
      if filas_de_datos(bloque) > 1 then
        n_tabla = n_tabla + 1
        agregar(texto('Rotulo', 'Tabla ' .. n_tabla))
        agregar(texto('RotuloTitulo', ultimo_titulo))
      end
      agregar(bloque)

    elseif bloque.t == 'CodeBlock' and bloque.classes:includes('mermaid') then
      -- EX-7: el diagrama ya dibujado por Chrome, en el mismo orden.
      n_figura = n_figura + 1
      local figura = figuras[n_figura]
      agregar(texto('Rotulo', 'Figura ' .. n_figura))
      agregar(texto('RotuloTitulo', ultimo_titulo))
      if figura then
        agregar(parrafo('FiguraImagen', { pandoc.Image({}, figura.ruta, '', { width = figura.ancho }) }))
      end

    elseif bloque.t == 'HorizontalRule' then
      -- Los separadores del .md no tienen sentido en papel.

    else
      agregar(bloque)
    end
  end

  doc.blocks = salida
  return doc
end
