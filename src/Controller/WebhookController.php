<?php

namespace App\Controller;

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
}
