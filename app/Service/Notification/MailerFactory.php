<?php

declare(strict_types=1);

namespace App\Service\Notification;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

class MailerFactory
{
    public function __invoke(ContainerInterface $container): MailerInterface
    {
        $config = $container->get(ConfigInterface::class);

        $host = (string) ($config->get('mail.host') ?? 'mailhog');
        $port = (int) ($config->get('mail.port') ?? 1025);
        $username = (string) ($config->get('mail.username') ?? '');
        $password = (string) ($config->get('mail.password') ?? '');
        $encryption = (string) ($config->get('mail.encryption') ?? '');

        // Build DSN for Symfony Mailer
        $dsn = $this->buildDsn($host, $port, $username, $password, $encryption);

        $transport = Transport::fromDsn($dsn);

        return new Mailer($transport);
    }

    private function buildDsn(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption
    ): string {
        // For Mailhog (no auth, no encryption)
        if (empty($username) && empty($password)) {
            return sprintf('smtp://%s:%d', $host, $port);
        }

        // For authenticated SMTP
        $scheme = $encryption === 'tls' || $encryption === 'ssl' ? 'smtps' : 'smtp';

        return sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            urlencode($username),
            urlencode($password),
            $host,
            $port
        );
    }
}
