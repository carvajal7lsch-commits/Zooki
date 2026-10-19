<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/CatalogoClinica.php';
require_once __DIR__ . '/../helpers/ValidadorConfiguracionClinica.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/** RE-7.4.5, RN-701: gestión de marca/tipos exclusivamente en el contexto administrativo. */
final class ConfiguracionClinica extends ModeloClinica
{
    private const CAMPOS_MARCA = 'id_clinica, nombre, direccion, telefono, telefono_urgencias, email_contacto, url_logo';

    private function exigirAdministrador(): void
    {
        if ((Contexto::actual()['tipo'] ?? '') !== Contexto::CLINICA || Contexto::rolActivo() !== Roles::ADMIN) {
            $this->denegarAcceso('clinicas', Contexto::clinicaActiva(), 'Gestión de configuración sin contexto administrador (RN-701)');
        }
    }

    private function administradorVigente(bool $bloquear): void
    {
        $fin = $bloquear && $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        // B1: primero clínica, luego usuario y su vínculo; las lecturas de decisión son actuales.
        $q = $this->conn->prepare("SELECT id_clinica FROM clinicas WHERE id_clinica = ? AND estado = 'activa'" . $fin);
        $q->execute([$this->clinica()]);
        $clinica = $q->fetchColumn();
        $q = $this->conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND estado = 1' . $fin);
        $q->execute([Contexto::idUsuario()]);
        $usuario = $q->fetchColumn();
        $q = $this->conn->prepare("SELECT id_rol FROM usuario_clinica WHERE id_clinica = ? AND id_usuario = ? AND estado = 'activo'" . $fin);
        $q->execute([$this->clinica(), Contexto::idUsuario()]);
        if (!$clinica || !$usuario || (int) $q->fetchColumn() !== Roles::ADMIN) {
            throw new AccesoDenegado(403, 'No tienes acceso a la configuración.');
        }
    }

    public function leer(): array
    {
        $this->exigirAdministrador();
        $this->administradorVigente(false);
        return ['marca' => $this->marca(), 'tipos' => (new CatalogoClinica($this->conn))->tiposCita(true)];
    }

    private function marca(): array
    {
        $q = $this->conn->prepare('SELECT ' . self::CAMPOS_MARCA . ' FROM clinicas WHERE id_clinica = ?');
        $q->execute([$this->clinica()]);
        return $q->fetch(PDO::FETCH_ASSOC);
    }

    private function escribir(callable $accion, string $tabla, ?int $id, array $entrada): mixed
    {
        $this->exigirAdministrador();
        if (isset($entrada['id_clinica']) && (!is_scalar($entrada['id_clinica']) || (string) $entrada['id_clinica'] !== (string) $this->clinica())) {
            $this->denegarAcceso('clinicas', null, 'Contexto de configuración ajeno');
        }
        // Estos nuevos escritores abren el protocolo desde su inicio; no adquieren clínica tarde.
        if ($this->conn->inTransaction()) {
            throw new LogicException('La configuración necesita su transacción exterior desde el candado de clínica.');
        }
        try {
            return Transaccion::ejecutar($this->conn, function () use ($accion) {
                $this->administradorVigente(true);
                return $accion();
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso($tabla, $id, 'Configuración fuera del contexto autorizado (RE-7.4.5)');
        }
    }

    private function auditar(string $tabla, int $id, ?array $antes, array $despues): void
    {
        if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), $antes === null ? 'INSERT' : 'UPDATE', $tabla, $id,
            $antes, $despues, 'Configuración de marca/tipos (HU-7.4, RE-7.4.5)', $this->clinica())) {
            throw new RuntimeException('No se pudo auditar la configuración.');
        }
    }

    public function guardarMarca(array $entrada): array
    {
        $this->exigirAdministrador();
        $datos = ValidadorConfiguracionClinica::datos($entrada);
        return $this->escribir(function () use ($datos) {
            $antes = $this->marca();
            $this->conn->prepare('UPDATE clinicas SET nombre = ?, direccion = ?, telefono = ?, telefono_urgencias = ?, email_contacto = ?, url_logo = ? WHERE id_clinica = ?')
                ->execute([...array_values($datos), $this->clinica()]);
            $despues = $this->marca();
            $this->auditar('clinicas', $this->clinica(), $antes, $despues);
            return $despues;
        }, 'clinicas', $this->clinica(), $entrada);
    }

    private function tipoPropio(int $id): array
    {
        $fin = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $q = $this->conn->prepare('SELECT * FROM tipos_cita WHERE id_clinica = ? AND id_tipo_cita = ?' . $fin);
        $q->execute([$this->clinica(), $id]);
        $tipo = $q->fetch(PDO::FETCH_ASSOC);
        if ($tipo === false) {
            throw new AccesoDenegado(403, 'No tienes acceso a este tipo.');
        }
        return $tipo;
    }

    public function guardarTipo(?int $id, array $entrada): array
    {
        $this->exigirAdministrador();
        $datos = ValidadorConfiguracionClinica::datos($entrada, true);
        return $this->escribir(function () use ($id, $datos) {
            $antes = $id === null ? null : $this->tipoPropio($id);
            if ($id === null) {
                $q = $this->conn->prepare('INSERT INTO tipos_cita (id_clinica, nombre_tipo, duracion_minutos, margen_minutos, pausable) VALUES (?, ?, ?, ?, ?)');
                $q->execute([$this->clinica(), ...array_values($datos)]);
                $id = (int) $this->conn->lastInsertId();
            } else {
                $this->conn->prepare('UPDATE tipos_cita SET nombre_tipo = ?, duracion_minutos = ?, margen_minutos = ?, pausable = ? WHERE id_clinica = ? AND id_tipo_cita = ?')
                    ->execute([...array_values($datos), $this->clinica(), $id]);
            }
            $despues = $this->tipoPropio($id);
            $this->auditar('tipos_cita', $id, $antes, $despues);
            return $despues;
        }, 'tipos_cita', $id, $entrada);
    }

    public function cambiarEstado(int $id, array $entrada): array
    {
        $this->exigirAdministrador();
        $error = ValidadorConfiguracionClinica::campo('activo', $entrada['activo'] ?? '');
        if ($error !== null) {
            throw new CampoInvalido('activo', $error, $error);
        }
        return $this->escribir(function () use ($id, $entrada) {
            $antes = $this->tipoPropio($id);
            $this->conn->prepare('UPDATE tipos_cita SET activo = ? WHERE id_clinica = ? AND id_tipo_cita = ?')
                ->execute([(int) $entrada['activo'], $this->clinica(), $id]);
            $despues = $this->tipoPropio($id);
            $this->auditar('tipos_cita', $id, $antes, $despues);
            return $despues;
        }, 'tipos_cita', $id, $entrada);
    }
}
