<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\ContentType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contents')]
#[ORM\Index(columns: ['type'], name: 'idx_content_type')]
#[ORM\Index(columns: ['score'], name: 'idx_content_score')]
#[ORM\Index(columns: ['published_at'], name: 'idx_published_at')]
class Content
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $providerId;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $title;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ContentType::class)]
    private ContentType $type;

    #[ORM\Column(type: Types::JSON)]
    private array $metrics;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $publishedAt;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $score = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $providerId,
        string $title,
        ContentType $type,
        array $metrics,
        DateTimeImmutable $publishedAt
    ) {
        $this->providerId = $providerId;
        $this->title = $title;
        $this->type = $type;
        $this->metrics = $metrics;
        $this->publishedAt = $publishedAt;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): ContentType
    {
        return $this->type;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getPublishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(float $score): void
    {
        $this->score = $score;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
