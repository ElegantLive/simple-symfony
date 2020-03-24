<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/24
 * Time: 15:21
 */

namespace App\MessageHandler;


use App\Message\ExceptionNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ExceptionNotificationHandler implements MessageHandlerInterface
{
    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * SignUpNotificationHandler constructor.
     * @param MailerInterface $mailer
     */
    public function __construct (MailerInterface $mailer)
    {
        $this->mailer     = $mailer;
    }

    /**
     * @param ExceptionNotification $exceptionNotification
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function __invoke (ExceptionNotification $exceptionNotification)
    {
        $exception = $exceptionNotification->getException();
        $code = $exception->getCode();

        $flattenException = FlattenException::create($exception, $code, $exceptionNotification->getRequest()->headers->all());

        $throwType = 'exception_full';
        $renderType = 'html';
        $templateName = sprintf('@Twig/Exception/%s.%s.twig', $throwType, $renderType);

        // send email
        $email = (new TemplatedEmail())->from($exceptionNotification->getSender())
            ->to($exceptionNotification->getReceiver())
            ->subject(sprintf('[symfony]-怎么又有bug-%s', date('Y-M-D H:i:s')))
            ->htmlTemplate($templateName)
            ->context([
                'status_code' => $code,
                'status_text' => isset(Response::$statusTexts[$code]) ? Response::$statusTexts[$code] : '',
                'exception' => $flattenException,
                'logger' => null,
                'currentContent' => null,
            ]);

        $this->mailer->send($email);
    }
}