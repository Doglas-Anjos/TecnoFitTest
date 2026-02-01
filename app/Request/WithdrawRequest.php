<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class WithdrawRequest extends FormRequest
{
    private const ALLOWED_PIX_TYPES = ['email', 'cpf', 'cnpj', 'phone', 'random'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedPixTypes = implode(',', self::ALLOWED_PIX_TYPES);

        return [
            'method' => 'required|string|in:PIX',
            'pix' => 'required|array',
            'pix.type' => "required|string|in:{$allowedPixTypes}",
            // Max 77 chars = email limit (largest PIX key type per BACEN spec)
            'pix.key' => 'required|string|max:77',
            'amount' => 'required|numeric|gt:0',
            'schedule' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'O método de saque é obrigatório.',
            'method.in' => 'Método de saque inválido. Apenas PIX é suportado.',
            'pix.required' => 'Os dados do PIX são obrigatórios.',
            'pix.type.required' => 'O tipo de chave PIX é obrigatório.',
            'pix.type.in' => 'Tipo de chave PIX inválido. Apenas email é suportado.',
            'pix.key.required' => 'A chave PIX é obrigatória.',
            'pix.key.max' => 'A chave PIX deve ter no máximo 77 caracteres.',
            'amount.required' => 'O valor do saque é obrigatório.',
            'amount.numeric' => 'O valor do saque deve ser numérico.',
            'amount.gt' => 'O valor do saque deve ser maior que zero.',
            'schedule.date' => 'A data de agendamento deve ser uma data válida.',
            'schedule.after' => 'A data de agendamento deve ser no futuro.',
        ];
    }

    public function attributes(): array
    {
        return [
            'method' => 'método',
            'pix.type' => 'tipo de chave PIX',
            'pix.key' => 'chave PIX',
            'amount' => 'valor',
            'schedule' => 'agendamento',
        ];
    }
}
