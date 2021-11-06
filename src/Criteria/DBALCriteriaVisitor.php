<?php declare(strict_types=1);

namespace PcComponentes\CriteriaDBALAdapter\Criteria;

use Doctrine\DBAL\Query\QueryBuilder;
use Pccomponentes\Criteria\Domain\Criteria\AndFilter;
use Pccomponentes\Criteria\Domain\Criteria\Criteria;
use Pccomponentes\Criteria\Domain\Criteria\Filter;
use Pccomponentes\Criteria\Domain\Criteria\FilterInterface;
use Pccomponentes\Criteria\Domain\Criteria\FilterOperator;
use Pccomponentes\Criteria\Domain\Criteria\FilterVisitorInterface;
use Pccomponentes\Criteria\Domain\Criteria\OrFilter;

final class DBALCriteriaVisitor implements FilterVisitorInterface
{
    private QueryBuilder $queryBuilder;
    private int $countParams;
    private array $criteriaToMapFields;

    public function __construct(QueryBuilder $queryBuilder, array $criteriaToMapFields = [])
    {
        $this->queryBuilder = $queryBuilder;
        $this->countParams = 0;
        $this->criteriaToMapFields = $criteriaToMapFields;
    }

    public function execute(Criteria $criteria): void
    {
        foreach ($criteria->filters() as $theFilter) {
            $this->queryBuilder->andWhere($this->buildExpression($theFilter));
        }

        if ($criteria->hasOrder()) {
            $this->queryBuilder->orderBy(
                $this->mapFieldValue($criteria->order()->orderBy()->value()),
                $criteria->order()->orderType()->value(),
            );
        }

        if (null !== $criteria->offset()) {
            $this->queryBuilder->setFirstResult($criteria->offset());
        }

        if (null === $criteria->limit()) {
            return;
        }

        $this->queryBuilder->setMaxResults($criteria->limit());
    }

    public function visitAnd(AndFilter $filter): string
    {
        return '( '. $this->buildExpression($filter->left()) .' AND '. $this->buildExpression($filter->right()) .' )';
    }

    public function visitOr(OrFilter $filter): string
    {
        return '( '. $this->buildExpression($filter->left()) .' OR '. $this->buildExpression($filter->right()) .' )';
    }

    public function visitFilter(Filter $filter): string
    {
        $this->countParams++;

        $this->queryBuilder->setParameter(
            $filter->field()->value() . $this->countParams,
            $this->mapParameter($filter),
            $this->mapType($filter),
        );

        return $this->mapFieldValue($filter->field()->value())
            . ' '
            . $this->mapOperator($filter)
            . ' '
            . $this->parameterExpression($filter);
    }

    private function parameterExpression(Filter $filter): string
    {
        if (\in_array($filter->operator()->value(), [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL], true)) {
            return '';
        }

        if (\in_array($filter->operator()->value(), [FilterOperator::IN, FilterOperator::NOT_IN])) {
            return '(:' . $filter->field()->value() . $this->countParams . ')';
        }

        return ':' . $filter->field()->value() . $this->countParams;
    }

    private function buildExpression(FilterInterface $filter)
    {
        return $filter->accept($this);
    }

    private function mapType(Filter $filter): ?int
    {
        if (\in_array($filter->operator()->value(), [FilterOperator::IN, FilterOperator::NOT_IN])) {
            return \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        return null;
    }

    private function mapOperator(Filter $filter): string
    {
        if (FilterOperator::CONTAINS === $filter->operator()->value()) {
            return 'LIKE';
        }

        if (FilterOperator::NOT_EQUAL === $filter->operator()->value()) {
            return '<>';
        }

        return $filter->operator()->value();
    }

    private function mapParameter(Filter $filter)
    {
        if (FilterOperator::CONTAINS === $filter->operator()->value()) {
            return '%' . $filter->value()->value() . '%';
        }

        return $filter->value()->value();
    }

    private function mapFieldValue($value)
    {
        return \array_key_exists($value, $this->criteriaToMapFields)
            ? $this->criteriaToMapFields[$value]
            : $value;
    }
}
