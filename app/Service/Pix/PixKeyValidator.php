<?php

declare(strict_types=1);

namespace App\Service\Pix;

use App\Exception\BusinessException;

/**
 * Validates PIX keys according to BACEN (Central Bank of Brazil) specifications.
 *
 * PIX Key Max Lengths (BACEN specification):
 * - Email:  77 characters (valid email format)
 * - CPF:    11 digits (numbers only, no formatting)
 * - CNPJ:   14 digits (numbers only, no formatting)
 * - Phone:  14 characters (format: +5511999999999)
 * - Random: 36 characters (UUID v4 format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
 */
class PixKeyValidator
{
    private const VALID_PIX_TYPES = ['email', 'cpf', 'cnpj', 'phone', 'random'];

    // BACEN PIX key length limits
    private const MAX_LENGTH_EMAIL = 77;
    private const MAX_LENGTH_CPF = 11;
    private const MAX_LENGTH_CNPJ = 14;
    private const MAX_LENGTH_PHONE = 14;
    private const MAX_LENGTH_RANDOM = 36;

    public function validate(string $type, string $key): void
    {
        if (!in_array($type, self::VALID_PIX_TYPES, true)) {
            throw new BusinessException(
                sprintf('Tipo de chave PIX inválido: %s. Tipos permitidos: %s', $type, implode(', ', self::VALID_PIX_TYPES)),
                422
            );
        }

        match ($type) {
            'email' => $this->validateEmail($key),
            'cpf' => $this->validateCpf($key),
            'cnpj' => $this->validateCnpj($key),
            'phone' => $this->validatePhone($key),
            'random' => $this->validateRandom($key),
        };
    }

    private function validateEmail(string $key): void
    {
        if (!filter_var($key, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException('Chave PIX do tipo email deve ser um email válido.', 422);
        }

        if (strlen($key) > self::MAX_LENGTH_EMAIL) {
            throw new BusinessException(
                sprintf('Chave PIX do tipo email deve ter no máximo %d caracteres.', self::MAX_LENGTH_EMAIL),
                422
            );
        }
    }

    private function validateCpf(string $key): void
    {
        // TODO: Implement CPF validation
        // - Must have exactly MAX_LENGTH_CPF (11) digits
        // - Must contain only numbers
        // - Must pass CPF checksum algorithm
        throw new BusinessException('Validação de chave PIX do tipo CPF ainda não implementada.', 501);
    }

    private function validateCnpj(string $key): void
    {
        // TODO: Implement CNPJ validation
        // - Must have exactly MAX_LENGTH_CNPJ (14) digits
        // - Must contain only numbers
        // - Must pass CNPJ checksum algorithm
        throw new BusinessException('Validação de chave PIX do tipo CNPJ ainda não implementada.', 501);
    }

    private function validatePhone(string $key): void
    {
        // TODO: Implement phone validation
        // - Must have exactly MAX_LENGTH_PHONE (14) characters
        // - Format: +5511999999999 (+55 country code + 2 digit DDD + 9 digit number)
        throw new BusinessException('Validação de chave PIX do tipo telefone ainda não implementada.', 501);
    }

    private function validateRandom(string $key): void
    {
        // TODO: Implement random key (EVP) validation
        // - Must have exactly MAX_LENGTH_RANDOM (36) characters
        // - Must be valid UUID v4 format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        throw new BusinessException('Validação de chave PIX do tipo aleatória ainda não implementada.', 501);
    }

    public function getSupportedTypes(): array
    {
        return self::VALID_PIX_TYPES;
    }

    public function isTypeImplemented(string $type): bool
    {
        return $type === 'email';
    }
}
