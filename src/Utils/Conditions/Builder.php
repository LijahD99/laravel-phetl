<?php

namespace Windsor\Phetl\Utils\Conditions;

use Illuminate\Support\Collection;

class Builder
{
    protected array $conditions = [];

    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '==', '===', '<', '>', '<=', '>=', '<>', '!=', '!==', '<=>', 'in', 'between', 'instanceof',
    ];


    public static function make(): static
    {
        return new static();
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function where(
        array|callable|string $field,
        mixed $operator = null,
        mixed $value = null,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        /**
         * If the field is an array, we will assume it is either an array of
         * key-value pairs, or an array of condition arrays.
         */
        if (is_array($field)) {
            return $this->addArrayOfConditions($field, $conjunction, 'where');
        }

        // If the field is actually a Closure instance, we will assume the developer
        // wants to begin a nested condition which is wrapped in parentheses.
        // We will add that Closure to the filter and return back out immediately.
        if ($field instanceof \Closure && is_null($operator)) {
            return $this->whereNested($field, $conjunction, $negate);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '=='];
        }

        // If the value is "null", we will just assume the developer wants to add a where null condition. So, we will allow a short-cut here to that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            $strict = in_array($operator, ['===', '!==']);
            return $this->whereNull($field, $strict, $conjunction, $negate);
        }

        // Now that we are working with just a simple filter we can put the elements in our array
        $this->conditions[] = new Where(
            $field,
            $operator,
            $value,
            $conjunction,
            $negate
        );

        return $this;
    }

    /**
     * Prepare the value and operator for a condition.
     *
     * @param  string  $value
     * @param  string  $operator
     * @param  bool  $use_default
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator(
        $value,
        $operator,
        $use_default = false,
    ) {
        if ($use_default) {
            return [$operator, '=='];
        }

        // perform some standardization on the operator
        $operator = match ($operator) {
            'IN', 'BETWEEN' => strtolower($operator),
            '=' => '==',
            '<>' => '!=',
            default => $operator,
        };

        if ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue(
        string $operator,
        mixed $value,
    ):bool {
        return is_null($value)
            && in_array($operator, $this->operators)
            && !in_array($operator, ['==', '===', '!=', '!==']);
    }

    /**
     * Determine if the given operator is supported.
     *
     * @param  string  $operator
     * @return bool
     */
    protected function invalidOperator(string $operator): bool
    {
        return ! is_string($operator)
            || (! in_array(strtolower($operator), $this->operators, true));
    }

    public function addArrayOfConditions(
        array $conditions,
        string $conjunction = 'and',
        string $method = 'where',
    ): static {
        return $this->whereNested(
            function ($builder) use ($conditions, $conjunction, $method) {
                foreach ($conditions as $key => $value) {
                    if (is_numeric($key) && is_array($value)) {
                        $builder->{$method}(
                            ...array_values($value),
                            conjunction: $conjunction
                        );
                    }
                    else {
                        $builder->{$method}($key, '==', $value, $conjunction);
                    }
                }
            },
            $conjunction
        );
    }

    public function whereNested(
        \Closure $callback,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $builder = new static();
        $callback($builder);

        $this->conditions[] = new NestedCondition(
            $builder->conditions,
            $conjunction,
            $negate
        );

        return $this;
    }

    public function orWhere(
        array|string|\Closure $field,
        mixed $operator = null,
        mixed $value = null,
        bool $negate = false,
    ): static {
        $this->where($field, $operator, $value, 'or', $negate);
        return $this;
    }

    public function whereNot(
        array|string|\Closure $field,
        mixed $operator = null,
        mixed $value = null,
        string $conjunction = 'and',
    ): static {
        $this->where($field, $operator, $value, $conjunction, true);
        return $this;
    }

    public function orWhereNot(
        array|string|\Closure $field,
        mixed $operator = null,
        mixed $value = null,
    ): static {
        $this->where($field, $operator, $value, 'or', true);
        return $this;
    }

    public function whereStrict(
        string $field,
        mixed $value,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $this->where($field, '===', $value, $conjunction, $negate);
        return $this;
    }

    public function whereNotStrict(
        string $field,
        mixed $value,
        string $conjunction = 'and',
    ): static {
        return $this->whereStrict($field, $value, $conjunction, true);
    }

    public function orWhereStrict(
        string $field,
        mixed $value,
        bool $negate = false,
    ): static {
        return $this->whereStrict($field, $value, 'or', $negate);
    }


    public function orWhereNotStrict(string $field, mixed $value): static
    {
        return $this->whereStrict($field, $value, 'or', true);
    }

    public function whereIn(
        string $field,
        array $values,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $this->where($field, 'in', $values, $conjunction, $negate);
        return $this;
    }

    public function whereNotIn(
        string $field,
        array $values,
        string $conjunction = 'and',
    ): static {
        return $this->whereIn($field, $values, $conjunction, true);
    }

    public function orWhereIn(
        string $field,
        array $values,
        bool $negate = false,
    ): static {
        return $this->whereIn($field, $values, 'or', $negate);
    }

    public function orWhereNotIn(string $field, array $values): static
    {
        return $this->whereIn($field, $values, 'or', true);
    }

    public function whereBetween(
        string $field,
        array $values,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('Between filter requires 2 values.');
        }

        $this->where($field, 'between', $values, $conjunction, $negate);

        return $this;
    }

    public function whereNotBetween(
        string $field,
        array $values,
        string $conjunction = 'and',
    ): static {
        return $this->whereBetween($field, $values, $conjunction, true);
    }

    public function orWhereBetween(
        string $field,
        array $values,
        bool $negate = false,
    ): static {
        return $this->whereBetween($field, $values, 'or', $negate);
    }

    public function orWhereNotBetween(string $field, array $values)
    {
        return $this->whereBetween($field, $values, 'or', true);
    }

    public function whereNull(
        string $field,
        bool $strict = false,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $operator = $strict ? '===' : '==';

        $this->conditions[] = new Where(
            $field,
            $operator,
            null,
            $conjunction,
            $negate
        );

        return $this;
    }

    public function whereNotNull(
        string $field,
        bool $strict = false,
        string $conjunction = 'and',
    ): static {
        return $this->whereNull($field, $strict, $conjunction, true);
    }

    public function orWhereNull(
        string $field,
        bool $strict = false,
        bool $negate = false,
    ): static {
        return $this->whereNull($field, $strict, 'or', $negate);
    }

    public function orWhereNotNull(string $field, bool $strict = false): static
    {
        return $this->whereNull($field, $strict, 'or', true);
    }

    public function whereInstanceOf(
        string $field,
        string $instance,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $this->where($field, 'instanceof', $instance, $conjunction, $negate);
        return $this;
    }

    public function orWhereInstanceOf(
        string $field,
        string $instance,
        bool $negate = false,
    ): static {
        return $this->whereInstanceOf($field, $instance, 'or', $negate);
    }

    public function whereNotInstanceOf(
        string $field,
        string $instance,
        string $conjunction = 'and',
    ): static {
        return $this->whereInstanceOf($field, $instance, $conjunction, true);
    }

    public function orWhereNotInstanceOf(
        string $field,
        string $instance,
    ): static {
        return $this->whereInstanceOf($field, $instance, 'or', true);
    }

    public function whereColumn(
        string $first,
        string $operator,
        array|string|null $second = null,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        if (is_null($second)) {
            $second = $operator;
            $operator = '==';
        }

        $this->conditions[] = new WhereColumn(
            $first,
            $operator,
            $second,
            $conjunction,
            $negate
        );
        return $this;
    }

    public function orWhereColumn(
        string $first,
        string $operator,
        ?string $second = null,
        bool $negate = false,
    ): static {
        $this->whereColumn($first, $operator, $second, 'or', $negate);
        return $this;
    }

    public function whereColumnNot(
        string $first,
        string $operator,
        ?string $second = null,
        string $conjunction = 'and',
    ): static {
        $this->whereColumn($first, $operator, $second, $conjunction, true);
        return $this;
    }

    public function orWhereColumnNot(
        string $first,
        string $operator,
        ?string $second = null,
    ): static {
        $this->whereColumn($first, $operator, $second, 'or', true);
        return $this;
    }

    public function whereColumnStrict(
        string $first,
        string $second,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $this->whereColumn($first, '===', $second, $conjunction, $negate);
        return $this;
    }

    public function whereColumnNotStrict(
        string $first,
        string $second,
        string $conjunction = 'and',
    ): static {
        return $this->whereColumnStrict($first, $second, $conjunction, true);
    }

    public function orWhereColumnStrict(
        string $first,
        string $second,
        bool $negate = false,
    ): static {
        return $this->whereColumnStrict($first, $second, 'or', $negate);
    }

    public function orWhereColumnNotStrict(string $first, string $second): static
    {
        return $this->whereColumnStrict($first, $second, 'or', true);
    }

    public function whereColumnIn(
        string $first,
        array|Collection $columns,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        $this->whereColumn($first, 'in', $columns, $conjunction, $negate);
        return $this;
    }

    public function whereColumnNotIn(
        string $first,
        array $columns,
        string $conjunction = 'and',
    ): static {
        return $this->whereColumnIn($first, $columns, $conjunction, true);
    }

    public function orWhereColumnIn(
        string $first,
        array $columns,
        bool $negate = false,
    ): static {
        return $this->whereColumnIn($first, $columns, 'or', $negate);
    }

    public function orWhereColumnNotIn(string $first, array $columns)
    {
        return $this->whereColumnIn($first, $columns, 'or', true);
    }

    public function whereColumnBetween(
        string $first,
        array $columns,
        string $conjunction = 'and',
        bool $negate = false,
    ): static {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('Between filter requires 2 values.');
        }

        $this->whereColumn($first, 'between', $columns, $conjunction, $negate);

        return $this;
    }

    public function whereColumnNotBetween(
        string $first,
        array $columns,
        string $conjunction = 'and',
    ): static {
        return $this->whereColumnBetween($first, $columns, $conjunction, true);
    }

    public function orWhereColumnBetween(
        string $first,
        array $columns,
        bool $negate = false,
    ): static {
        return $this->whereColumnBetween($first, $columns, 'or', $negate);
    }

    public function orWhereColumnNotBetween(string $first, array $columns)
    {
        return $this->whereColumnBetween($first, $columns, 'or', true);
    }

    /**
     * Evaluates the given data against the specified conditions.
     *
     * @param mixed $row The data to be evaluated.
     * @param mixed $conditions The conditions to evaluate the data against.
     * @return mixed The result of the evaluation.
     */
    public function evaluate($row)
    {
        if (empty($this->conditions)) {
            return false;
        }

        $nested_condition = $this->build();

        return $nested_condition->check($row);
    }

    public function build(): NestedCondition
    {
        return new NestedCondition($this->conditions);
    }

    /**
     * alias for evaluate
     *
     * @param [type] $row
     * @return boolean
     */
    public function check($row): bool
    {
        return $this->evaluate($row);
    }

    /**
     * alias for evaluate
     *
     * @param [type] $row
     * @return boolean
     */
    public function run($row): bool
    {
        return $this->check($row);
    }
}