<?php
namespace App\Controller\TransactionController;

use App\Controller\CurrentUserTrait;
use App\Entity\TransactionEntity;
use App\Form\TransactionType;
use App\Repository\TransactionRepo;
use App\Service\CurrencySummary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TransactionController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly TransactionRepo $transactionRepo,
        private readonly FormFactoryInterface $formFactory,
        private readonly CurrencySummary $currencySummary,
    ) {
    }

    #[Route('/transaction', name: 'transaction')]
    public function index(): Response
    {
        return $this->renderTransactionPage();
    }

    #[Route('/transaction/create', name: 'transaction_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $transaction = new TransactionEntity();
        $form = $this->createTransactionForm($transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $transaction->setUserId($this->currentUserId());

            $entityManager->persist($transaction);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Transaction "%s" has been added.', $transaction->getDescription()));

            return $this->redirectToRoute('transaction');
        }

        return $this->renderTransactionPage(
            createForm: $form,
            openModal: 'createTransactionModal',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    #[Route('/transaction/{transaction}/edit', name: 'transaction_edit', methods: ['POST'])]
    public function edit(TransactionEntity $transaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwned($transaction);

        $form = $this->createEditForm($transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Transaction "%s" has been updated.', $transaction->getDescription()));

            return $this->redirectToRoute('transaction');
        }

        return $this->renderTransactionPage(
            editForms: [$transaction->getTransactionId() => $form],
            openModal: 'editTransactionModal'.$transaction->getTransactionId(),
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    #[Route('/transaction/{transaction}/delete', name: 'transaction_delete', methods: ['POST'])]
    public function delete(TransactionEntity $transaction, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwned($transaction);

        if (!$this->isCsrfTokenValid('delete_transaction_'.$transaction->getTransactionId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Unable to delete this transaction. Please try again.');

            return $this->redirectToRoute('transaction');
        }

        $entityManager->remove($transaction);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Transaction "%s" has been deleted.', $transaction->getDescription()));

        return $this->redirectToRoute('transaction');
    }

    /**
     * @param array<int, FormInterface> $editForms Already submitted forms, keyed by transaction id
     */
    private function renderTransactionPage(
        ?FormInterface $createForm = null,
        array $editForms = [],
        ?string $openModal = null,
        int $status = Response::HTTP_OK,
    ): Response {
        $transactions = $this->transactionRepo->findAllOrderedByNewestForUser($this->currentUserId());

        $editViews = [];
        foreach ($transactions as $transaction) {
            $id = $transaction->getTransactionId();
            $form = $editForms[$id] ?? $this->createEditForm($transaction);
            $editViews[$id] = $form->createView();
        }

        return $this->render('/Transaction/Transaction.html.twig', [
            'transactions' => $transactions,
            'totalAmount' => array_sum(array_map(
                static fn (TransactionEntity $transaction): float => (float) $transaction->getAmount(),
                $transactions
            )),
            'summaryCurrency' => $this->currencySummary->resolve(array_map(
                static fn (TransactionEntity $transaction): ?string => $transaction->getBudget()?->getCurrencyType(),
                $transactions
            )),
            'createForm' => ($createForm ?? $this->createTransactionForm(new TransactionEntity()))->createView(),
            'editForms' => $editViews,
            'openModal' => $openModal,
        ], new Response(null, $status));
    }

    private function denyUnlessOwned(TransactionEntity $transaction): void
    {
        if ($transaction->getUserId() !== $this->currentUserId()) {
            throw $this->createAccessDeniedException('This transaction belongs to another account.');
        }
    }

    private function createTransactionForm(TransactionEntity $transaction): FormInterface
    {
        return $this->formFactory->create(TransactionType::class, $transaction, [
            'action' => $this->generateUrl('transaction_create'),
            'method' => 'POST',
            'user_id' => $this->currentUserId(),
        ]);
    }

    private function createEditForm(TransactionEntity $transaction): FormInterface
    {
        return $this->formFactory->createNamed(
            'transaction_edit_'.$transaction->getTransactionId(),
            TransactionType::class,
            $transaction,
            [
                'action' => $this->generateUrl('transaction_edit', ['transaction' => $transaction->getTransactionId()]),
                'method' => 'POST',
                'user_id' => $this->currentUserId(),
            ],
        );
    }
}
