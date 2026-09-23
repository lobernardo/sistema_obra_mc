<?php

namespace App\Enums;

/**
 * Origin of an account created outside the Gestão users area (CT-07 c,
 * RF-22): public Novo Cadastro or convite acceptance.
 */
enum AccountOrigin: string
{
    case NovoCadastro = 'novo_cadastro';
    case Convite = 'convite';
}
