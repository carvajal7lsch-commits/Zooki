<?php

interface ClienteWompi
{
    public function transaccion(string $id): array;

    public function comercio(): array;
}
