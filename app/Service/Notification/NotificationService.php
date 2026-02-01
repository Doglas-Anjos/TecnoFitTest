<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Model\AccountWithdraw;
use Hyperf\Contract\ConfigInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private ConfigInterface $config,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Send withdrawal confirmation email asynchronously (non-blocking)
     * Uses Swoole coroutine to spawn email sending in background
     */
    public function sendWithdrawConfirmationAsync(AccountWithdraw $withdraw, string $recipientEmail): void
    {
        // Pre-build email data before spawning coroutine
        $emailData = $this->prepareEmailData($withdraw, $recipientEmail);
        $withdrawId = $withdraw->id;

        // Spawn a new coroutine for email sending (truly non-blocking)
        \Swoole\Coroutine::create(function () use ($emailData, $recipientEmail, $withdrawId) {
            try {
                // Create a fresh mailer instance in the coroutine
                $dsn = sprintf(
                    'smtp://%s:%s@%s:%d',
                    urlencode($emailData['smtpUsername']),
                    urlencode($emailData['smtpPassword']),
                    $emailData['smtpHost'],
                    $emailData['smtpPort']
                );

                $transport = Transport::fromDsn($dsn);
                $mailer = new Mailer($transport);

                $email = (new Email())
                    ->from($emailData['from'])
                    ->to($emailData['to'])
                    ->subject($emailData['subject'])
                    ->html(self::buildHtmlFromData($emailData))
                    ->text(self::buildTextFromData($emailData));

                $mailer->send($email);

                // Log success (can't use injected logger in new coroutine)
                echo "[EMAIL] Sent to {$recipientEmail} for withdrawal #{$withdrawId}\n";
            } catch (\Throwable $e) {
                echo "[EMAIL ERROR] Failed to send to {$recipientEmail}: {$e->getMessage()}\n";
            }
        });
    }

    private static function buildHtmlFromData(array $data): string
    {
        return "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 10px; border-bottom: 1px solid #eee; }
        .details td:first-child { font-weight: bold; width: 40%; }
        .amount { font-size: 24px; color: #4CAF50; font-weight: bold; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>Saque Realizado com Sucesso!</h1>
        </div>
        <div class=\"content\">
            <p>Olá <strong>{$data['accountName']}</strong>,</p>
            <p>Seu saque via PIX foi processado com sucesso.</p>
            <div class=\"details\">
                <table>
                    <tr><td>Valor:</td><td class=\"amount\">R$ {$data['amount']}</td></tr>
                    <tr><td>Data e Hora:</td><td>{$data['dateTime']}</td></tr>
                    <tr><td>Método:</td><td>PIX</td></tr>
                    <tr><td>Tipo de Chave:</td><td>{$data['pixTypeLabel']}</td></tr>
                    <tr><td>Chave PIX:</td><td>{$data['pixKey']}</td></tr>
                    <tr><td>ID da Transação:</td><td><code>{$data['withdrawId']}</code></td></tr>
                </table>
            </div>
            <p>Se você não reconhece esta transação, entre em contato conosco imediatamente.</p>
        </div>
        <div class=\"footer\">
            <p>Este é um email automático. Por favor, não responda.</p>
            <p>&copy; TecnoFit - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>";
    }

    private static function buildTextFromData(array $data): string
    {
        return "TECNOFIT - CONFIRMAÇÃO DE SAQUE PIX

Olá {$data['accountName']},

Seu saque via PIX foi processado com sucesso.

DETALHES DA TRANSAÇÃO:
- Valor: R$ {$data['amount']}
- Data e Hora: {$data['dateTime']}
- Método: PIX
- Tipo de Chave: {$data['pixTypeLabel']}
- Chave PIX: {$data['pixKey']}
- ID da Transação: {$data['withdrawId']}

Se você não reconhece esta transação, entre em contato conosco imediatamente.

---
Este é um email automático. Por favor, não responda.
© TecnoFit - Todos os direitos reservados";
    }

    /**
     * Send withdrawal confirmation email synchronously (blocking)
     */
    public function sendWithdrawConfirmation(AccountWithdraw $withdraw, string $recipientEmail): bool
    {
        try {
            $email = $this->buildWithdrawEmail($withdraw, $recipientEmail);

            $this->mailer->send($email);

            $this->logger->info(sprintf(
                'Withdrawal confirmation email sent to %s for withdrawal #%s',
                $recipientEmail,
                $withdraw->id
            ));

            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to send withdrawal confirmation email to %s for withdrawal #%s: %s',
                $recipientEmail,
                $withdraw->id,
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Prepare email data as plain array (safe for coroutine)
     */
    private function prepareEmailData(AccountWithdraw $withdraw, string $recipientEmail): array
    {
        $pix = $withdraw->pix;
        $account = $withdraw->account;

        return [
            'to' => $recipientEmail,
            'from' => $this->config->get('mail.from.address', 'noreply@tecnofit.com'),
            'subject' => sprintf(
                'TecnoFit - Confirmação de Saque PIX - R$ %s',
                number_format((float) $withdraw->amount, 2, ',', '.')
            ),
            'withdrawId' => $withdraw->id,
            'amount' => number_format((float) $withdraw->amount, 2, ',', '.'),
            'dateTime' => $withdraw->created_at->format('d/m/Y H:i:s'),
            'accountName' => $account->name,
            'pixType' => $pix->type,
            'pixTypeLabel' => $this->getPixTypeLabel($pix->type),
            'pixKey' => $pix->key,
            // SMTP config for async sending
            'smtpHost' => $this->config->get('mail.host', 'localhost'),
            'smtpPort' => (int) $this->config->get('mail.port', 25),
            'smtpUsername' => $this->config->get('mail.username', ''),
            'smtpPassword' => $this->config->get('mail.password', ''),
        ];
    }


    private function buildWithdrawEmail(AccountWithdraw $withdraw, string $recipientEmail): Email
    {
        $pix = $withdraw->pix;
        $account = $withdraw->account;

        $subject = sprintf(
            'TecnoFit - Confirmação de Saque PIX - R$ %s',
            number_format((float) $withdraw->amount, 2, ',', '.')
        );

        $htmlContent = $this->buildEmailHtml($withdraw, $account, $pix);
        $textContent = $this->buildEmailText($withdraw, $account, $pix);

        $fromAddress = $this->config->get('mail.from.address', 'noreply@tecnofit.com');

        return (new Email())
            ->from($fromAddress)
            ->to($recipientEmail)
            ->subject($subject)
            ->html($htmlContent)
            ->text($textContent);
    }

    private function buildEmailHtml(AccountWithdraw $withdraw, $account, $pix): string
    {
        $amount = number_format((float) $withdraw->amount, 2, ',', '.');
        $dateTime = $withdraw->created_at->format('d/m/Y H:i:s');
        $pixType = $this->getPixTypeLabel($pix->type);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .details table { width: 100%; border-collapse: collapse; }
        .details td { padding: 10px; border-bottom: 1px solid #eee; }
        .details td:first-child { font-weight: bold; width: 40%; }
        .amount { font-size: 24px; color: #4CAF50; font-weight: bold; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Saque Realizado com Sucesso!</h1>
        </div>
        <div class="content">
            <p>Olá <strong>{$account->name}</strong>,</p>
            <p>Seu saque via PIX foi processado com sucesso.</p>

            <div class="details">
                <table>
                    <tr>
                        <td>Valor:</td>
                        <td class="amount">R$ {$amount}</td>
                    </tr>
                    <tr>
                        <td>Data e Hora:</td>
                        <td>{$dateTime}</td>
                    </tr>
                    <tr>
                        <td>Método:</td>
                        <td>PIX</td>
                    </tr>
                    <tr>
                        <td>Tipo de Chave:</td>
                        <td>{$pixType}</td>
                    </tr>
                    <tr>
                        <td>Chave PIX:</td>
                        <td>{$pix->key}</td>
                    </tr>
                    <tr>
                        <td>ID da Transação:</td>
                        <td><code>{$withdraw->id}</code></td>
                    </tr>
                </table>
            </div>

            <p>Se você não reconhece esta transação, entre em contato conosco imediatamente.</p>
        </div>
        <div class="footer">
            <p>Este é um email automático. Por favor, não responda.</p>
            <p>&copy; TecnoFit - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function buildEmailText(AccountWithdraw $withdraw, $account, $pix): string
    {
        $amount = number_format((float) $withdraw->amount, 2, ',', '.');
        $dateTime = $withdraw->created_at->format('d/m/Y H:i:s');
        $pixType = $this->getPixTypeLabel($pix->type);

        return <<<TEXT
TECNOFIT - CONFIRMAÇÃO DE SAQUE PIX

Olá {$account->name},

Seu saque via PIX foi processado com sucesso.

DETALHES DA TRANSAÇÃO:
- Valor: R$ {$amount}
- Data e Hora: {$dateTime}
- Método: PIX
- Tipo de Chave: {$pixType}
- Chave PIX: {$pix->key}
- ID da Transação: {$withdraw->id}

Se você não reconhece esta transação, entre em contato conosco imediatamente.

---
Este é um email automático. Por favor, não responda.
© TecnoFit - Todos os direitos reservados
TEXT;
    }

    private function getPixTypeLabel(string $type): string
    {
        return match ($type) {
            'email' => 'E-mail',
            'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'phone' => 'Telefone',
            'random' => 'Chave Aleatória',
            default => $type,
        };
    }
}
