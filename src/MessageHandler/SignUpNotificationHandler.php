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
     * @var string
     */
    private $from;

    /**
     * SignUpNotificationHandler constructor.
     * @param UserRepository  $userRepository
     * @param MailerInterface $mailer
     * @param string          $from
     */
    public function __construct (UserRepository $userRepository, MailerInterface $mailer, string $from)
    {

        $this->userRepository = $userRepository;
        $this->mailer         = $mailer;
        $this->from           = $from;
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
        $email = (new TemplatedEmail())->from($this->from)
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