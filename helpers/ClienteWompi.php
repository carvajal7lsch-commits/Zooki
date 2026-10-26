<?php

interface ClienteWompi
{
    public function transaccion(string $id): array;

    public function comercio(): array;

    public function tokenNequi(string $token): array;

    public function crearFuente(array $datos): array;

    public function crearTransaccion(array $datos): array;
}
