<?php
namespace App\Entity;

use App\Repository\BudgetRepo;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetRepo::class)]
#[ORM\Table(name: 'budget')]
#[ORM\HasLifecycleCallbacks]
class BudgetEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'budget_id', type: 'integer')]
    private ?int $budgetId = null;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'b_description', type: 'string', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Please give this budget a description.')]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\NotBlank(message: 'Please enter an amount.')]
    #[Assert\Positive(message: 'The amount must be greater than zero.')]
    private ?string $amount = null;

    #[ORM\Column(name: 'currency_type', type: 'string', length: 3, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['PHP', 'USD', 'EUR'], message: 'Choose a valid currency.')]
    private ?string $currencyType = 'PHP';

    #[ORM\Column(name: 'create_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'update_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBudgetId(): ?int
    {
        return $this->budgetId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrencyType(): ?string
    {
        return $this->currencyType;
    }

    public function setCurrencyType(?string $currencyType): static
    {
        $this->currencyType = $currencyType;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
