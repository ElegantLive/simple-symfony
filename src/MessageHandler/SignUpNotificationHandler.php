<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/22
 * Time: 22:36
 */

namespace App\MessageHandler;


use App\Message\SignUpNotification;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class SignUpNotificationHandler implements MessageHandlerInterface
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * SignUpNotificationHandler constructor.
     * @param UserRepository  $userRepository
     * @param MailerInterface $mailer
     */
    public function __construct (UserRepository $userRepository, MailerInterface $mailer)
    {

        $this->userRepository = $userRepository;
        $this->mailer         = $mailer;
    }

    /**
     * @param SignUpNotification $signUpNotification
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function __invoke (SignUpNotification $signUpNotification)
    {
        $user = $this->userRepository->findOneBy(['id' => $signUpNotification->getUid()]);
        if (empty($user)) return;

        // send email
        $email = (new TemplatedEmail())->from('qq52577517@163.com')
            ->to($user->getEmail())
            ->subject('thanks for your sign up')
            ->htmlTemplate('emails/signup.html.twig')
            ->context([
                'expiration_date' => new \DateTime('+7 days'),
                'username'        => $user->getName(),
            ]);

        $this->mailer->send($email);
    }
}