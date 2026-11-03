<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/Suscripcion.php';

/** HU-0.1/0.5: intención aislada por clínica, independiente de la vigencia contratada. */
final class IntencionProfesional extends ModeloClinica
{
    private function exigirAdministrador(): void
    {
        if (Contexto::rolActivo() !== Roles::ADMIN) throw new AccesoDenegado(403, 'Esta elección corresponde al administrador.');
    }

    private function fila(): array
    {
        $this->exigirAdministrador();
        $q = $this->conn->prepare('SELECT intencion_profesional_ciclo,intencion_profesional_mostrada_en FROM clinicas WHERE id_clinica=?');
        $q->execute([$this->clinica()]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: throw new InvalidArgumentException('La clínica no está disponible.');
    }

    public function actual(): ?string
    {
        $fila = $this->fila();
        if ($fila['intencion_profesional_ciclo'] === null) return null;
        return (new Suscripcion($this->conn, $this->clinica()))->tienePagosAprobados() ? null : $fila['intencion_profesional_ciclo'];
    }

    public function primerAcceso(): ?string
    {
        $fila = $this->fila();
        if ($fila['intencion_profesional_ciclo'] === null || $fila['intencion_profesional_mostrada_en'] !== null) return null;
        return (new Suscripcion($this->conn, $this->clinica()))->bloqueada(function (): ?string {
            $fila = $this->fila();
            $ciclo = $this->actual();
            if ($ciclo === null || $fila['intencion_profesional_mostrada_en'] !== null) return null;
            $q = $this->conn->prepare('UPDATE clinicas SET intencion_profesional_mostrada_en=CURRENT_TIMESTAMP WHERE id_clinica=? AND intencion_profesional_mostrada_en IS NULL');
            $q->execute([$this->clinica()]);
            return $q->rowCount() === 1 ? $ciclo : null;
        });
    }

    public function descartar(): void
    {
        $this->exigirAdministrador();
        $servicio = new Suscripcion($this->conn, $this->clinica());
        $servicio->bloqueada(function () use ($servicio): void {
            $antes = $this->fila();
            if ($antes['intencion_profesional_ciclo'] === null) return;
            $this->conn->prepare('UPDATE clinicas SET intencion_profesional_ciclo=NULL,intencion_profesional_mostrada_en=NULL WHERE id_clinica=?')->execute([$this->clinica()]);
            $servicio->auditar('clinicas', $this->clinica(), $antes, ['intencion_profesional_ciclo' => null], 'Continuar con el plan Gratis: intención Profesional descartada (HU-0.5)', Contexto::idUsuario());
        });
    }
}
