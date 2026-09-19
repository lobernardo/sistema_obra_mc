<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates the human-readable `pedido` code (`PED-000001`) from a
 * PostgreSQL sequence, so concurrent creations never collide: `nextval()`
 * is atomic at the database level regardless of how many processes call it
 * simultaneously.
 */
class PedidoCodeGenerator
{
    public function generate(): string
    {
        $value = DB::selectOne("select nextval('pedido_code_sequence') as value")->value;

        return sprintf('PED-%06d', $value);
    }
}
