<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress = 'no-reply@rayan.store',
    ) {
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($order->getUser()->getEmail())
            ->subject(sprintf('Confirmation de commande %s', $order->getOrderNumber()))
            ->text(sprintf(
                "Merci pour votre commande %s.\nTotal : %s EUR\n",
                $order->getOrderNumber(),
                $order->getTotalAmount()
            ));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // A failed confirmation email must never roll back a placed order;
            // the order is already committed. Log and move on.
            $this->logger->error('Failed to send order confirmation email', [
                'order' => $order->getOrderNumber(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendVerificationEmail(User $user, string $token): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Vérifiez votre adresse email')
            ->text(sprintf('Votre code de vérification : %s', $token));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send verification email', ['error' => $e->getMessage()]);
        }
    }
}
