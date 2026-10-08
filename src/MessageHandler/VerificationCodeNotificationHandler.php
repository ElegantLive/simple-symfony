<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/26
 * Time: 19:56
 */

namespace App\MessageHandler;


use App\Message\VerificationCodeNotification;
use App\Service\VerificationCode;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class VerificationCodeNotificationHandler implements MessageHandlerInterface
{
    /**
     * @var MailerInterface
     */
    private $mailer;

    private $titleMap = [
        VerificationCode::REGISTER        => '您正在注册demo博客',
        VerificationCode::CHANGE_PASSWORD => '您正在修改密码',
    ];

    /**
     * VerificationCodeNotificationHandler constructor.
     *
     * No UserRepository: the recipient is part of the message. Resolving it from
     * a uid could not work for registration, where no account exists yet, and it
     * failed silently when the lookup came back empty.
     *
     * @param MailerInterface $mailer
     */
    public function __construct (MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param VerificationCodeNotification $codeNotification
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function __invoke (VerificationCodeNotification $codeNotification)
    {
        $minutes = $codeNotification->getTime() / 60;
        $title   = $this->titleMap[$codeNotification->getType()];

        $email = (new TemplatedEmail())->from($codeNotification->getFrom())
            ->to($codeNotification->getEmail())
            ->subject($title)
            ->htmlTemplate('emails/verification_code.html.twig')
            ->context([
                'name'        => $codeNotification->getName(),
                'description' => $title,
                'minutes'     => $minutes,
                'code'        => $codeNotification->getCode()
            ]);

        $this->mailer->send($email);
    }
}
