<?php

/** El detalle interno solo se entrega al auditor; el controlador usa exclusivamente publica. */
final class PrescripcionRechazada extends InvalidArgumentException
{
    public function __construct(public readonly int $fila, public readonly array $publica, private readonly array $interna, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public function paraAuditoria(): array
    {
        return ['fila' => $this->fila, 'evaluacion' => $this->interna];
    }
}
