<?php
namespace App\Form;

use App\Entity\BudgetEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. Pamalengke budget',
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
            ->add('currencyType', ChoiceType::class, [
                'label' => 'Currency',
                'choices' => [
                    '₱ Philippine peso (PHP)' => 'PHP',
                    '$ US dollar (USD)' => 'USD',
                    '€ Euro (EUR)' => 'EUR',
                ],
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BudgetEntity::class,
        ]);
    }
}
