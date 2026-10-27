<?php
namespace App\Form;

use App\Entity\BudgetEntity;
use App\Entity\TransactionEntity;
use App\Repository\BudgetRepo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. Rice',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('budget', EntityType::class, [
                'label' => 'Budget',
                'class' => BudgetEntity::class,
                'required' => true,
                'placeholder' => 'Select a budget',
                'choice_label' => static fn (BudgetEntity $budget): string => $budget->getDescription() ?? 'Untitled budget',
                'query_builder' => static function (BudgetRepo $repo) use ($options) {
                    $queryBuilder = $repo->createQueryBuilder('b')
                        ->orderBy('b.description', 'ASC');

                    if (null === $options['user_id']) {
                        $queryBuilder->andWhere('1 = 0');
                    } else {
                        $queryBuilder
                            ->andWhere('b.userId = :userId')
                            ->setParameter('userId', $options['user_id']);
                    }

                    return $queryBuilder;
                },
                'attr' => ['class' => 'form-select'],
                'help' => 'The transaction is linked by the budget description, not its ID.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransactionEntity::class,
            'user_id' => null,
        ]);
        $resolver->setAllowedTypes('user_id', ['int', 'null']);
    }
}
