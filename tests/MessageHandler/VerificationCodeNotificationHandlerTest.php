<?php

namespace App\Tests\MessageHandler;

use App\Message\VerificationCodeNotification;
use App\MessageHandler\VerificationCodeNotificationHandler;
use App\Service\VerificationCode;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\RawMessage;

/**
 * The regression guard for the register flow.
 *
 * This handler used to resolve the recipient with
 * UserRepository::find($notification->getUid()) and `return` quietly when that
 * came back empty. Registration happens before an account exists, so the lookup
 * always came back empty there: the endpoint answered 发送成功 and no mail was ever
 * sent. The recipient is now carried by the message, and these tests fail if that
 * ever goes back to depending on a user row.
 */
final class VerificationCodeNotificationHandlerTest extends TestCase
{
    public function testItMailsTheCodeToTheRecipientInTheMessage(): void
    {
        $mailer = new CapturingMailer();
        $handler = new VerificationCodeNotificationHandler($mailer);

        $handler($this->notification([
            'type'  => VerificationCode::REGISTER,
            'email' => 'newcomer@example.com',
            'name'  => '',
            'code'  => 123456,
            'from'  => 'from@example.com',
            'time'  => 720,
        ]));

        $this->assertCount(1, $mailer->sent, 'exactly one mail should be sent');

        /** @var TemplatedEmail $email */
        $email = $mailer->sent[0];

        $this->assertSame('newcomer@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('from@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('您正在注册demo博客', $email->getSubject());
        $this->assertSame('emails/verification_code.html.twig', $email->getHtmlTemplate());
    }

    public function testTheTemplateContextCarriesTheCodeAndExpiry(): void
    {
        $mailer = new CapturingMailer();
        $handler = new VerificationCodeNotificationHandler($mailer);

        $handler($this->notification([
            'type'  => VerificationCode::CHANGE_PASSWORD,
            'email' => 'someone@example.com',
            'name'  => 'Someone',
            'code'  => 654321,
            'from'  => 'from@example.com',
            'time'  => 300,
        ]));

        /** @var TemplatedEmail $email */
        $email   = $mailer->sent[0];
        $context = $email->getContext();

        $this->assertSame('您正在修改密码', $email->getSubject());
        $this->assertSame(654321, $context['code']);
        $this->assertSame('Someone', $context['name']);
        $this->assertSame(5, $context['minutes'], 'the template is given minutes, not seconds');
    }

    private function notification(array $fields): VerificationCodeNotification
    {
        return new VerificationCodeNotification($fields);
    }
}

/**
 * Records what would have been sent instead of delivering it.
 */
final class CapturingMailer implements MailerInterface
{
    /** @var RawMessage[] */
    public $sent = [];

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): void
    {
        $this->sent[] = $message;
    }
}
