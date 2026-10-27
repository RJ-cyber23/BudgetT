<?php

namespace App\Controller\ReportController;

use App\Controller\CurrentUserTrait;
use App\Service\SavingsReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    use CurrentUserTrait;

    #[Route('/report', name: 'report')]
    public function savings(SavingsReport $savingsReport): Response
    {
        return $this->render('/Report/Report.html.twig', $savingsReport->generate(
            $this->currentUserId(),
            $this->currentUser()->getUserIdentifier(),
        ));
    }
}
