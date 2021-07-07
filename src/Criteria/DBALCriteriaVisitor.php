<?php declare(strict_types=1);

namespace PcComponentes\CriteriaDBALAdapter\Criteria;

use Doctrine\DBAL\Query\QueryBuilder;
use Pccomponentes\Criteria\Domain\Criteria\AndFilter;
use Pccomponentes\Criteria\Domain\Criteria\Criteria;
use Pccomponentes\Criteria\Domain\Criteria\FilterInterface;
use Pccomponentes\Criteria\Domain\Criteria\NullValueFilter;
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

    public function visitFilter(FilterInterface $filter): string
    {
        $this->countParams++;

        $this->queryBuilder->setParameter(
            $filter->field()->value() . $this->countParams,
            $this->mapParameter($filter),
            $this->mapType($filter),
        );

        if ($filter instanceof NullValueFilter) {
            return $this->mapFieldValue($filter->field()->value())
                . ' '
                . $filter->operator()->value();
        }

        return $this->mapFieldValue($filter->field()->value())
            . ' '
            . $this->mapOperator($filter)
            . (\in_array($filter->operator()->value(), [FilterOperator::IN, FilterOperator::NOT_IN]) ? ' (' : ' ')
            . ':' . $filter->field()->value()
            . $this->countParams
            . (\in_array($filter->operator()->value(), [FilterOperator::IN, FilterOperator::NOT_IN]) ? ')' : '');
    }

    private function buildExpression(FilterInterface $filter)
    {
        return $filter->accept($this);
    }

    private function mapType(FilterInterface $filter): ?int
    {
        if (\in_array($filter->operator()->value(), [FilterOperator::IN, FilterOperator::NOT_IN])) {
            return \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        return null;
    }

    private function mapOperator(FilterInterface $filter): string
    {
        if (FilterOperator::CONTAINS === $filter->operator()->value()) {
            return 'LIKE';
        }

        if (FilterOperator::NOT_EQUAL === $filter->operator()->value()) {
            return '<>';
        }

        if (FilterOperator::GTE === $filter->operator()->value()) {
            return '>=';
        }

        if (FilterOperator::LTE === $filter->operator()->value()) {
            return '<=';
        }

        return $filter->operator()->value();
    }

    private function mapParameter(FilterInterface $filter)
    {
        if (\in_array($filter->operator()->value(), [FilterOperator::IS_NULL, FilterOperator::IS_NOT_NULL])) {
            return;
        }

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
