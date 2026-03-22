<?php

namespace App\Controller;

use App\Entity\ChatExportUploadLink;
use App\Exception\Service\ChatExportFileHandler\InvalidMimeTypeException;
use App\Form\ChatExportUploadType;
use App\Message\ProcessChatExportMessage;
use App\Service\ChatExportFileHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ChatExportUploadController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Nutgram         $nutgram
    )
    {
    }

    #[Route(
        '/upload/{token:upload}',
        name: 'app_chat_export_upload',
        requirements: ['token' => '[a-f0-9]{64}'],
        methods: ['GET', 'POST']
    )]
    public function index(
        Request                     $request,
        ChatExportUploadLink        $upload,
        RateLimiterFactoryInterface $fileUploadLimiter,
        EntityManagerInterface      $em,
        MessageBusInterface         $messageBus,
        ChatExportFileHandler       $fileHandler,
    ): RedirectResponse|JsonResponse|Response
    {

        $limiter = $fileUploadLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Too many uploads. Try again later.');
        }

        if ($upload->isUsed() || $upload->isExpired()) {
            return $this->render('expired_link.twig');
        }

        $bot = $upload->getBot();

        $form = $this->createForm(ChatExportUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();

            if (!$file) {
                $this->addFlash('error', 'No file was uploaded.');
                return $this->redirectToRoute('app_chat_export_upload', ['token' => $upload->getToken()]);
            }

            try {
                $chatExportFile = $fileHandler->handleWebUpload($bot->getOwner(), $file);

                $bot->setChatExportFile($chatExportFile);
                $bot->setIsBeingTrained(true);
                $upload->setResultedFile($chatExportFile);
                $upload->setIsUsed(true);
                $em->flush();

                $messageBus->dispatch(
                    new ProcessChatExportMessage($chatExportFile->getId(), $bot->getId())
                );

                $this->notifyUser(
                    $bot->getOwner()->getTelegramId(),
                    "⏳ Processing your file... I'll let you know when it's ready!"
                );

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'status' => 'success',
                        'targetUrl' => $this->generateUrl('app_chat_export_upload_success'),
                    ]);
                }

                return $this->redirectToRoute('app_chat_export_upload_success');
            } catch (InvalidMimeTypeException $e) {
                $this->addFlash('error', '❌ Invalid file type. Please upload a JSON file.');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Upload failed. Please try again.');
            }
        }

        return $this->render('upload.html.twig', [
            'form' => $form,
            'upload' => $upload,
            'bot' => $bot,
        ]);
    }

    private function notifyUser(int $chatId, string $message): void
    {
        try {
            $this->nutgram->sendMessage($message, chat_id: $chatId);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to notify user about web upload status', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }

    #[Route('/upload/success', name: 'app_chat_export_upload_success')]
    public function success(): Response
    {
        return $this->render('upload_success.html.twig');
    }
}
