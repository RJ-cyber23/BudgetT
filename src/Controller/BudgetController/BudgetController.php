<?php
namespace App\Controller\BudgetController;

use App\Controller\CurrentUserTrait;
use App\Entity\BudgetEntity;
use App\Form\BudgetType;
use App\Repository\BudgetRepo;
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
class BudgetController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly BudgetRepo $budgetRepo,
        private readonly FormFactoryInterface $formFactory,
        private readonly CurrencySummary $currencySummary,
    ) {
    }

    #[Route('/budget', name: 'budget')]
    public function index(): Response
    {
        return $this->renderBudgetPage();
    }

    #[Route('/budget/create', name: 'budget_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budget = new BudgetEntity();
        $form = $this->createBudgetForm($budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $budget->setUserId($this->currentUserId());

            $entityManager->persist($budget);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Budget "%s" has been added.', $budget->getDescription()));

            return $this->redirectToRoute('budget');
        }

        return $this->renderBudgetPage(
            createForm: $form,
            openModal: 'createBudgetModal',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    #[Route('/budget/{budget}/edit', name: 'budget_edit', methods: ['POST'])]
    public function edit(BudgetEntity $budget, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwned($budget);

        $form = $this->createEditForm($budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Budget "%s" has been updated.', $budget->getDescription()));

            return $this->redirectToRoute('budget');
        }

        return $this->renderBudgetPage(
            editForms: [$budget->getBudgetId() => $form],
            openModal: 'editBudgetModal'.$budget->getBudgetId(),
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    #[Route('/budget/{budget}/delete', name: 'budget_delete', methods: ['POST'])]
    public function delete(BudgetEntity $budget, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwned($budget);

        if (!$this->isCsrfTokenValid('delete_budget_'.$budget->getBudgetId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Unable to delete this budget. Please try again.');

            return $this->redirectToRoute('budget');
        }

        $entityManager->remove($budget);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Budget "%s" has been deleted.', $budget->getDescription()));

        return $this->redirectToRoute('budget');
    }

    /**
     * @param array<int, FormInterface> $editForms Already submitted forms, keyed by budget id
     */
    private function renderBudgetPage(
        ?FormInterface $createForm = null,
        array $editForms = [],
        ?string $openModal = null,
        int $status = Response::HTTP_OK,
    ): Response {
        $budgets = $this->budgetRepo->findAllOrderedByNewestForUser($this->currentUserId());

        $editViews = [];
        foreach ($budgets as $budget) {
            $id = $budget->getBudgetId();
            $form = $editForms[$id] ?? $this->createEditForm($budget);
            $editViews[$id] = $form->createView();
        }

        return $this->render('/Budget/Budget.html.twig', [
            'budgets' => $budgets,
            'totalAmount' => array_sum(array_map(
                static fn (BudgetEntity $budget): float => (float) $budget->getAmount(),
                $budgets
            )),
            'summaryCurrency' => $this->currencySummary->resolve(array_map(
                static fn (BudgetEntity $budget): ?string => $budget->getCurrencyType(),
                $budgets
            )),
            'createForm' => ($createForm ?? $this->createBudgetForm(new BudgetEntity()))->createView(),
            'editForms' => $editViews,
            'openModal' => $openModal,
        ], new Response(null, $status));
    }

    private function denyUnlessOwned(BudgetEntity $budget): void
    {
        if ($budget->getUserId() !== $this->currentUserId()) {
            throw $this->createAccessDeniedException('This budget belongs to another account.');
        }
    }

    private function createBudgetForm(BudgetEntity $budget): FormInterface
    {
        return $this->formFactory->create(BudgetType::class, $budget, [
            'action' => $this->generateUrl('budget_create'),
            'method' => 'POST',
        ]);
    }

    private function createEditForm(BudgetEntity $budget): FormInterface
    {
        return $this->formFactory->createNamed(
            'budget_edit_'.$budget->getBudgetId(),
            BudgetType::class,
            $budget,
            [
                'action' => $this->generateUrl('budget_edit', ['budget' => $budget->getBudgetId()]),
                'method' => 'POST',
            ],
        );
    }
}
