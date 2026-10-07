<?php
require_once __DIR__ . '/../../models/Consulta.php';

/** Consulta que espera el candado de numeración 1 segundo en vez de 10, para las pruebas con MySQL. */
final class ConsultaConEsperaCorta extends Consulta
{
    protected const ESPERA_CANDADO_HC = 1;
}
