<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Standard web controller establishing core framework endpoints for Home routes.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function __invoke(): Response
    {
        return $this->render('home/index.html.twig', [
            'title' => 'Oneshot Fantasy',
        ]);
    }
}
