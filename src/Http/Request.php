<?php
// Encapsula os dados de um request HTTP (body + arquivos) e aplica validação

declare(strict_types=1);

namespace App\Http;

/*
 * Por que encapsular $_POST e $_FILES numa classe?
 * Em vez de acessar $_POST diretamente nos controllers, passamos tudo pro Request.
 * Isso torna o fluxo explícito e centraliza a validação:
 * Controller   -> cria Request com $_POST + $_FILES + validator
 *              -> chama validated()
 *              -> se inválido: ValidationException com lista de erros
 *              -> se válido: array de dados
 * Vantagem prática: o controller nunca precisa saber como validar, ele só sabe que, se validated() não lançar exceção, os dados estão ok.
 * Pesquise "Single Responsibility Principle" e "Form Request" (conceito do Laravel).
*/

final class Request
{
    /**
     * @param array<string, mixed>  $data      Dados do form ($_POST)
     * @param array<string, mixed>  $files     Arquivos enviados ($_FILES)
     * @param object|null           $validator  Objeto com método validate(array): array{errors: list<string>, data: array}
     *
     * Nota: o ideal seria tipar $validator com uma interface (ex: ValidatorInterface) pra garantir em tempo de compilação que o método validate() existe.
     * Por simplicidade do projeto, usamos object|null e documentamos o contrato aqui.
     */
    public function __construct(
        private array $data = [],
        private array $files = [],
        private ?object $validator = null,
    ) {
    }

    /**
     * Retorna os dados validados e sanitizados pelo validator.
     * Lança ValidationException se a validação falhar, o controller trata o erro.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validated(): array
    {
        if ($this->validator === null) {
            return $this->data;
        }

        /*
         * O validator retorna ['errors' => [...], 'data' => [...]].
         * Se errors não estiver vazio, lança a exceção com a lista.
         * O controller captura e re-renderiza o form com os erros.
        */
        $result = $this->validator->validate($this->data);

        if (!empty($result['errors'])) {
            throw new ValidationException('Dados inválidos', $result['errors']);
        }

        return $result['data'];
    }

    /**
     * Retorna os arquivos do request ($_FILES).
     *
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }
}
