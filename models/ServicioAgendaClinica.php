<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/OperacionAgenda.php';
require_once __DIR__ . '/EventoAgenda.php';
require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';
require_once __DIR__ . '/../helpers/ValidadorAgendaClinica.php';

/** B1: clínica, usuarios, mascotas y lecturas actuales en todos los comandos C6/C7. */
abstract class ServicioAgendaClinica extends ModeloClinica
{
    protected Closure $reloj;

    public function __construct(PDO $db, ?callable $reloj = null)
    {
        parent::__construct($db);
        $this->reloj = Closure::fromCallable($reloj ?? static fn () => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    protected function filas(string $sql, array $parametros = []): array
    {
        $q = $this->conn->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function autorizar(array $roles): void
    {
        if ((Contexto::actual()['tipo'] ?? '') !== Contexto::CLINICA || !in_array(Contexto::rolActivo(), $roles, true)) {
            $this->denegarAcceso('agenda', null, 'Acción fuera del contexto autorizado (RN-G01/G13)');
        }
        $vigente = $this->filas("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario JOIN clinicas c ON c.id_clinica=uc.id_clinica WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=? AND uc.estado='activo' AND u.estado=1 AND c.estado='activa'", [$this->clinica(), Contexto::idUsuario(), Contexto::rolActivo()]);
        if (!$vigente) {
            $this->denegarAcceso('agenda', null, 'Membresía o cuenta sin vigencia');
        }
    }

    protected function veterinario(int $id): void
    {
        if (!$this->filas("SELECT 1 FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1", [$this->clinica(), $id])) {
            $this->denegarAcceso('usuarios', $id, 'Veterinario no disponible en esta clínica');
        }
    }

    protected function recursos(array $mascotas = [], array $usuarios = [], ?array $rango = null): array
    {
        $vets = $this->filas('SELECT id_usuario FROM usuario_clinica WHERE id_clinica=? AND id_rol=2', [$this->clinica()]);
        $hoy = ($this->reloj)()->format('Y-m-d');
        $consulta = "SELECT id_veterinario,id_mascota FROM citas WHERE id_clinica=? AND (estado IN ('en_curso','pausada','sin_cerrar') OR fecha=?)";
        $parametros = [$this->clinica(), $hoy];
        if ($rango !== null) {
            $consulta = 'SELECT id_veterinario,id_mascota FROM citas WHERE id_clinica=? AND fecha>=? AND fecha<=?';
            $parametros = [$this->clinica(), substr($rango[0], 0, 10), substr($rango[1], 0, 10)];
        }
        if ($mascotas) {
            $marcas = implode(',', array_fill(0, count($mascotas), '?'));
            $consulta = 'SELECT id_veterinario,id_mascota FROM citas WHERE id_clinica=? AND (fecha=? OR estado IN (\'en_curso\',\'pausada\',\'sin_cerrar\') OR id_mascota IN (' . $marcas . '))';
            $parametros = [$this->clinica(), $hoy, ...$mascotas];
        }
        $citas = $this->filas($consulta, $parametros);
        $mascotas = array_values(array_unique([...$mascotas, ...array_column($citas, 'id_mascota')]));
        $titulares = [];
        if ($mascotas) {
            $marcas = implode(',', array_fill(0, count($mascotas), '?'));
            $titulares = array_column($this->filas("SELECT id_propietario FROM mascotas WHERE id_mascota IN ($marcas)", $mascotas), 'id_propietario');
        }
        return ['usuarios' => [...array_column($vets, 'id_usuario'), ...array_column($citas, 'id_veterinario'), ...$usuarios, ...$titulares], 'mascotas' => $mascotas];
    }

    protected function unidad(array $roles, string $accion, array $entrada, ?string $clave, callable $recursos, callable $escribir): array
    {
        $this->autorizar($roles);
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), $recursos, function () use ($roles, $accion, $entrada, $clave, $escribir): array {
                $this->autorizar($roles);
                return (new OperacionAgenda($this->conn))->ejecutar($accion, $clave, $entrada, $escribir);
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('agenda', null, 'Comando denegado tras rollback');
        }
    }

    protected function auditar(string $tabla, int $id, ?array $antes, array $despues, string $motivo): void
    {
        if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', $tabla, $id, $antes, $despues, $motivo, $this->clinica())) {
            throw new RuntimeException('No se pudo auditar la operación de agenda.');
        }
    }
}
