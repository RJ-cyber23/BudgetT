<?php

namespace App\Controller\HomeController;

use App\Controller\CurrentUserTrait;
use App\Repository\TransactionRepo;
use App\Service\SavingsReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class HomeController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly SavingsReport $savingsReport,
        private readonly TransactionRepo $transactionRepo,
    ) {
    }

    #[Route('/home', name: 'homepage')]
    public function home(): Response
    {
        $user = $this->currentUser();
        $userId = $this->currentUserId();
        $report = $this->savingsReport->generate($userId, $user->getUserIdentifier());

        return $this->render('/Home/Home.html.twig', [
            'rows' => $report['rows'],
            'totalBudget' => $report['totalBudget'],
            'totalSpent' => $report['totalSpent'],
            'totalRemaining' => $report['totalRemaining'],
            'uncategorizedSpent' => $report['uncategorizedSpent'],
            'summaryCurrency' => $report['currency'],
            'recentTransactions' => $this->transactionRepo->findRecentForUser($userId, 5),
        ]);
    }
}
