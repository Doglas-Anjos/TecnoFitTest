<?php

declare(strict_types=1);

namespace App\Service\Withdraw\Method;

use App\Exception\BusinessException;
use Hyperf\Contract\ContainerInterface;

class WithdrawMethodFactory
{
    /**
     * Map of method names to their implementation classes
     */
    private array $methods = [
        PixWithdrawMethod::METHOD_NAME => PixWithdrawMethod::class,
        // Future methods can be added here:
        // TedWithdrawMethod::METHOD_NAME => TedWithdrawMethod::class,
        // BoletoWithdrawMethod::METHOD_NAME => BoletoWithdrawMethod::class,
    ];

    public function __construct(
        private ContainerInterface $container
    ) {
    }

    /**
     * Create a withdraw method instance based on the method name
     *
     * @throws BusinessException
     */
    public function create(string $method): WithdrawMethodInterface
    {
        $method = strtoupper($method);

        if (!isset($this->methods[$method])) {
            throw new BusinessException(
                sprintf('Método de saque não suportado: %s', $method),
                422
            );
        }

        return $this->container->get($this->methods[$method]);
    }

    /**
     * Check if a method is supported
     */
    public function supports(string $method): bool
    {
        return isset($this->methods[strtoupper($method)]);
    }

    /**
     * Get all supported method names
     */
    public function getSupportedMethods(): array
    {
        return array_keys($this->methods);
    }

    /**
     * Register a new withdrawal method
     */
    public function register(string $methodName, string $className): void
    {
        $this->methods[strtoupper($methodName)] = $className;
    }
}
