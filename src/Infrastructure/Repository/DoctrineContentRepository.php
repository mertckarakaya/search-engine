<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Content;
use App\Domain\Repository\ContentRepositoryInterface;
use App\Domain\ValueObject\ContentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineContentRepository extends ServiceEntityRepository implements ContentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Content::class);
    }

    public function save(Content $content): void
    {
        $this->getEntityManager()->persist($content);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?Content
    {
        return $this->find($id);
    }

    public function search(
        ?string $keyword = null,
        ?ContentType $type = null,
        int $page = 1,
        int $limit = 10
    ): array {
        $qb = $this->createQueryBuilder('c');

        if ($keyword) {
            $qb->andWhere('LOWER(c.title) LIKE LOWER(:keyword)')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        if ($type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        $qb->orderBy('c.score', 'DESC')
            ->addOrderBy('c.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countSearch(?string $keyword = null, ?ContentType $type = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($keyword) {
            $qb->andWhere('LOWER(c.title) LIKE LOWER(:keyword)')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        if ($type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
