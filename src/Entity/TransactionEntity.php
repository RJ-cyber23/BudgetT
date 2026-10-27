<?php
namespace App\Entity;

use App\Repository\TransactionRepo;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransactionRepo::class)]
#[ORM\Table(name: '`transaction`')]
#[ORM\Index(name: 'IDX_TRANSACTION_USER', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class TransactionEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'transac_id', type: 'integer')]
    private ?int $transactionId = null;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    #[ORM\ManyToOne(targetEntity: BudgetEntity::class)]
    #[ORM\JoinColumn(name: 'budget_id', referencedColumnName: 'budget_id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choose the budget this transaction belongs to.')]
    private ?BudgetEntity $budget = null;

    #[ORM\Column(name: 't_description', type: 'string', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Please give this transaction a description.')]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Assert\NotBlank(message: 'Please enter an amount.')]
    #[Assert\Positive(message: 'The amount must be greater than zero.')]
    private ?string $amount = null;

    #[ORM\Column(name: 'create_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'update_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTransactionId(): ?int
    {
        return $this->transactionId;
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

    public function getBudget(): ?BudgetEntity
    {
        return $this->budget;
    }

    public function setBudget(?BudgetEntity $budget): static
    {
        $this->budget = $budget;

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
