<?php
// Validator de login: valida e-mail e senha antes de tentar autenticar

declare(strict_types=1);

namespace App\Validators;

/*
 * Por que validar login antes de consultar o banco?
 * Duas razões:
 * 1. Evita queries desnecessárias: um e-mail vazio ou malformado nunca vai existir no banco, não precisa perguntar.
 * 2. Mensagens de erro mais claras: "O e-mail é obrigatório" é mais útil para o usuário do que "E-mail ou senha incorretos."
 *
 * Importante: o validator NÃO verifica se o e-mail existe ou se a senha está correta.
 * Isso é responsabilidade do AuthService. O validator só garante que o formato da entrada está correto.
 */

final class LoginValidator
{
    /**
     * Valida os campos do formulário de login.
     *
     * @param array<string, mixed> $input
     * @return array{errors: list<string>, data: array<string, mixed>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $data   = [];

        $email = trim((string) ($input['email'] ?? ''));

        if ($email === '') {
            $errors[] = 'O e-mail é obrigatório.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            /*
             * filter_var() com FILTER_VALIDATE_EMAIL rejeita "usuario@" (sem domínio), "@dominio.com" (sem usuário), sem @, etc.
             * Pesquise "FILTER_VALIDATE_EMAIL PHP" para ver exatamente o que passa e o que não passa.
             */
            $errors[] = 'O e-mail informado não é válido.';
        }

        $data['email'] = $email;

        /*
         * Senha NÃO recebe trim(): espaços no início ou fim podem ser intencionais em alguns sistemas.
         * Fazer trim() silenciosamente mudaria o valor que o usuário digitou e causaria falhas de login inexplicáveis.
         * A única verificação aqui é se está vazio, que já captura o caso de não preencher o campo.
         */
        $password = (string) ($input['password'] ?? '');

        if ($password === '') {
            $errors[] = 'A senha é obrigatória.';
        }

        $data['password'] = $password;

        return ['errors' => $errors, 'data' => $data];
    }
}
