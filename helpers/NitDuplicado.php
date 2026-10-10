<?php

/** RN-010: excepción pública de unicidad; no expone la identidad del administrador. */
final class NitDuplicado extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Este NIT ya está registrado. Puedes reportarlo a soporte.');
    }
}
