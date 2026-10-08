<?php
/**
 * El horario pedido ya está ocupado (RN-401): por la validación de la
 * aplicación o por el índice único de la base (D-2), cuando otra reserva
 * llegó primero. Es una validación: el controlador responde 422 con el
 * mensaje, no un 500.
 */
final class HorarioOcupado extends InvalidArgumentException
{
}
