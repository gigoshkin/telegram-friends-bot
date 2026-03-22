<?php

namespace App\Controller;

use App\Service\ChatExportParser;
use SergiX44\Nutgram\Nutgram;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    #[Route('/hook', name: 'app_webhook')]
    public function index(Nutgram $bot): Response
    {
		$bot->run();

		return new Response();
    }

    #[Route('/test', name: 'app_test')]
    public function test(): Response
    {
        $parser = new ChatExportParser(
            $this->getParameter('kernel.project_dir') . '/chat_exports/2_7bc1ea76227da8c9.json'
        );

        $parser->getMembers();

        return new Response();
    }
}
