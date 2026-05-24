<?php
// Middleware de proteção CSRF: bloqueia submissões de formulário falsificadas

declare(strict_types=1);

namespace App\Middleware;

/*
 * O que é CSRF?
 * Cross-Site Request Forgery (CSRF) é um ataque onde um site malicioso induz o navegador do usuário a fazer uma request pra um site onde ele está autenticado.
 * Exemplo clássico sem proteção:
 * - Você está logado em bank.com
 * - Você visita crime.com, que tem um <form action="bank.com/transfer"> oculto
 * - O form é enviado automaticamente por JavaScript e o banco executa
 * Como nos protegemos:
 * 1. Ao renderizar um form, geramos um token aleatório e salvamos na sessão
 * 2. O form envia esse token como campo oculto (_csrf)
 * 3. Ao receber o POST, comparamos o token do form com o da sessão
 * 4. Se não baterem, rejeitamos a requisição
 * Isso funciona porque:
 * - O crime.com não consegue ler o valor da sessão (same-origin policy)
 * - Sem o token correto, o ataque falha
 * Limitação desta implementação: token único por sessão
 * Um único token é gerado na sessão e reutilizado em todos os formulários enquanto a sessão estiver ativa.
 * Isso significa que múltiplas abas do mesmo usuário compartilham o mesmo token, o que é relativamente seguro no nosso teste, mas não ideal.
 * A abordagem mais segura é o "per-form token" (ou "double submit cookie"): cada formulário recebe um token diferente, válido por tempo limitado.
 * Assim, reutilizar um token capturado de uma aba não compromete outra aba.
 * Pesquise: "CSRF per-form token", "synchronizer token pattern", "double submit cookie".
 * Para esta aplicação, um token por sessão é suficiente.
 * Pesquise: "OWASP CSRF", "SameSite cookie attribute"
 * O atributo SameSite=Lax do cookie de sessão já ajuda, mas CSRF no token é uma defesa adicional.
*/

final class CsrfMiddleware
{
    public static function verify(): void
    {
        /*
         * Só verificamos em POST real (REQUEST_METHOD do browser).
         * PUT e DELETE chegam aqui via method spoofing, o browser envia POST com _method=DELETE no body. O servidor vê POST, o token é validado.
         * PUT/DELETE reais (sem spoofing) não carregam token de sessão, então skippar é correto.
         * Pesquise "method spoofing CSRF".
         */
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return;
        }

        // Rotas de API não usam sessão PHP (autenticam por token no header)
        // Se a sessão não está ativa, não há CSRF pra verificar
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $tokenEnviado  = (string) ($_POST['_csrf'] ?? '');
        $tokenEsperado = (string) ($_SESSION['csrf_token'] ?? '');

        /*
         * hash_equals() em vez de === por segurança contra timing attacks: comparações simples podem vazar informação pelo tempo de resposta.
         * Pesquise "timing attack" e "constant time comparison".
        */
        if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenEnviado)) {
            http_response_code(419);
            // 419 = "Authentication Timeout" (convenção)
            echo 'Token CSRF inválido. Volte à página anterior e tente novamente.';
            exit;
        }
    }
}
