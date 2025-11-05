# Class Hierarchies - Phetl ETL Package

## 🏗️ **Class Structure Overview**

This document provides detailed class hierarchy analysis to help you understand the inheritance patterns, interfaces, and relationships throughout the Phetl codebase.

## 📊 **Master Class Hierarchy**

```
Windsor\Phetl\
├── Phetl (Main Facade - Empty)
├── PhetlServiceProvider (Laravel Integration)
├── TransformationPipeline (Pipeline Orchestrator)
│
├── Contracts/ (Interfaces)
│   ├── Extractor
│   ├── Transformer
│   └── Sequenceable
│
├── Concerns/ (Traits)
│   └── HasLifecycleHooks
│
├── Builders/ (Factory Classes)
│   ├── ExtractorBuilder ✅
│   ├── EtlProcessBuilder (Empty)
│   └── LoadBuilder (Missing)
│
├── Extractors/ (Strategy Pattern)
│   ├── BaseExtractor (Abstract)
│   ├── ApiExtractor ✅
│   ├── CsvExtractor ✅
│   ├── QueryExtractor ✅
│   └── AggregateExtractor (Broken)
│
├── Transformers/ (Strategy Pattern)
│   ├── RowTransformer (Abstract)
│   ├── ColumnTransformer (Abstract)
│   ├── AddField ✅
│   ├── Sequence ✅
│   ├── TransformerGroup ✅
│   ├── UnpackCompositeField ✅
│   ├── Converters/
│   │   ├── BaseConverter (Abstract)
│   │   ├── CastTypes ✅
│   │   ├── ConvertHeaderCasing ✅
│   │   ├── MapRows ✅
│   │   └── RenameHeaders ✅
│   └── Filters/
│       ├── BaseFilter (Abstract)
│       ├── CallbackFilter ✅
│       ├── CriteriaFilter ✅
│       ├── DateTimeThresholdFilter ✅
│       ├── DuplicatesFilter ✅
│       ├── OffsetFilter ✅
│       └── ValidatorFilter ✅
│
├── Utils/
│   └── Conditions/ (Query Builder Pattern)
│       ├── Builder ✅ (Excellent)
│       ├── Condition (Abstract)
│       ├── Where ✅
│       ├── WhereColumn ✅
│       ├── NestedCondition ✅
│       └── MakesComparisons (Trait)
│
├── Facades/ (Laravel Facades)
│   ├── Extract ✅
│   ├── Load (Points to missing LoadBuilder)
│   ├── Phetl ✅
│   └── Transform ✅
│
├── Commands/
│   └── PhetlCommand ✅
│
└── Enums/
    └── StringCaseType ✅
```

---

## 🎯 **Core Interface Definitions**

### **Extractor Interface** (`src/Contracts/Extractor.php`)

```php
interface Extractor {
    /**
     * Run the extraction process.
     * @return Enumerable
     */
    public function run(): Enumerable;
}
```

**Design Analysis**:
- ✅ **Simple & Focused**: Single method interface follows ISP
- ✅ **Polymorphism Ready**: All extractors interchangeable
- ⚠️ **Missing extract()**: BaseExtractor adds abstract extract() method

**Usage Pattern**:
```php
$extractor = Extract::fromApi($url);
$data = $extractor->run(); // Always returns Enumerable
```

### **Transformer Interface** (`src/Contracts/Transformer.php`)

```php
interface Transformer {
    public function transform(Enumerable $dataset): Enumerable;
}
```

**Design Analysis**:
- ✅ **Functional**: Pure transformation interface
- ✅ **Chainable**: Input and output are same type (Enumerable)
- ✅ **Predictable**: Always returns Enumerable

**Usage Pattern**:
```php
$transformer = new SomeTransformer();
$processed = $transformer->transform($data);
```

### **Sequenceable Interface** (`src/Contracts/Sequenceable.php`)

```php
interface Sequenceable {
    // Interface definition not provided in codebase
    // Likely for ordering transformations
}
```

---

## 🏭 **Extractor Hierarchy**

### **BaseExtractor** (Abstract Base Class)

```php
abstract class BaseExtractor implements Extractor {
    use HasLifecycleHooks;

    abstract public function extract(): Enumerable;

    public function run(): Enumerable {
        $this->runHooks('before-extraction', $this);
        $data = $this->extract();
        $this->runHooks('after-extraction', $this, $data);
        return $data;
    }

    public function __invoke(): Enumerable {
        return $this->run();
    }

    public function afterExtraction(callable $callback): static
    public function beforeExtraction(callable $callback): static
}
```

**Design Analysis**:
- ✅ **Template Method Pattern**: `run()` defines algorithm, `extract()` is customizable
- ✅ **Hook Integration**: Built-in lifecycle events
- ✅ **Invokable**: Can be used as callable
- ✅ **Fluent Interface**: Hook methods return `static`
- ⚠️ **Interface Inconsistency**: Interface defines `run()`, base defines `extract()`

### **ApiExtractor** (Concrete Implementation)

```php
class ApiExtractor extends BaseExtractor {
    protected PendingRequest $request;
    protected Response $response;
    protected string $method = 'get';
    protected string $endpoint;
    protected array $query_string;
    protected array $post_data;
    protected string $accepted_content_type;
    protected \Closure $error_handler;
    protected \Closure $parser;
    protected ?string $data_path = null;
    protected bool $lazy = false;

    // Configuration methods
    public function __call($method, $parameters) // Adapter pattern
    public function parseBodyWith(callable $parser): static
    public function dataPath(string $key): static
    public function method($method): static
    public function get($url, $query = []): static
    public function post($url, $data = []): static
    public function onError(callable $callback): static
    public function lazy(): static

    // Template method implementation
    public function extract(): Enumerable
}
```

**Design Strengths**:
- ✅ **Adapter Pattern**: Wraps Laravel HTTP client seamlessly
- ✅ **Flexible Configuration**: Multiple ways to configure requests
- ✅ **Error Handling**: Custom error callbacks
- ✅ **Memory Efficient**: LazyCollection support
- ✅ **Response Parsing**: Custom parsers or JSON path extraction

**Current Issues**:
- ⚠️ **Duplicate Request**: `sendRequest()` makes request twice
- ⚠️ **Error Handler Bug**: Wrong callback reference

### **CsvExtractor** (Concrete Implementation)

```php
class CsvExtractor extends BaseExtractor {
    protected SimpleExcelReader $reader;

    public function __construct(?string $path = null)
    public function path(string $path): static
    public function __call($method, $args) // Forward to SimpleExcel
    public function extract(): Enumerable
}
```

**Design Strengths**:
- ✅ **Adapter Pattern**: Clean wrapper around Spatie SimpleExcel
- ✅ **Magic Methods**: Preserves full library functionality
- ✅ **Fluent Interface**: Maintains chain-ability
- ✅ **Lazy Loading**: Defers file reading until extraction

### **QueryExtractor** (Concrete Implementation)

```php
class QueryExtractor extends BaseExtractor {
    protected string|QueryBuilder|EloquentBuilder $query;
    protected array $bindings = [];
    protected ?string $connection = null;

    public function __construct($query = null, array $bindings = [], ?string $connection = null)
    public function query($query): static
    public function raw(string $query, array $bindings = [], ?string $connection = null): static
    public function extract(): Enumerable
}
```

**Design Strengths**:
- ✅ **Flexible Input**: Supports raw SQL, Query Builder, Eloquent
- ✅ **Security**: Parameter binding prevents SQL injection
- ✅ **Multi-Connection**: Different database connections
- ✅ **Callable Support**: Query building via closures

### **AggregateExtractor** (Broken Implementation)

```php
class AggregateExtractor implements Extractor {
    public function extract() {} // Wrong return type, should be Enumerable
    // Missing: public function run(): Enumerable
}
```

**Issues**:
- ❌ **Interface Violation**: Doesn't implement required `run()` method
- ❌ **Wrong Return Type**: `extract()` should return `Enumerable`
- ❌ **No Functionality**: Empty implementation

**Intended Design**:
```php
class AggregateExtractor extends BaseExtractor {
    protected array $extractors = [];
    protected string $strategy = 'merge';

    public function add(Extractor $extractor): static
    public function merge(): static
    public function union(): static
    public function join(string $key): static
    public function extract(): Enumerable
}
```

---

## 🔄 **Transformer Hierarchy**

### **Abstract Base Classes**

#### **RowTransformer** (Template for Row Processing)

```php
abstract class RowTransformer implements Transformer {
    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map([$this, 'transformRow']);
    }

    abstract protected function transformRow($row);
}
```

**Design Analysis**:
- ✅ **Template Method**: Common pattern for row-by-row processing
- ✅ **Simple Override**: Subclasses only implement `transformRow()`
- ✅ **Collection Integration**: Uses Laravel Collection's `map()`

#### **ColumnTransformer** (Template for Column Processing)

```php
abstract class ColumnTransformer implements Transformer {
    private array|string $columns;

    public function __construct(array|string $columns)
    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function ($row) {
            foreach ($this->columns as $column) {
                if (!isset($row[$column])) continue;
                $row[$column] = $this->transformColumnValue($row[$column]);
            }
            return $row;
        });
    }

    abstract protected function transformColumnValue($column);
}
```

**Design Analysis**:
- ✅ **Focused Processing**: Only affects specified columns
- ✅ **Safe Operation**: Skips missing columns gracefully
- ✅ **Multi-Column Support**: Can process multiple columns at once
- ✅ **String Parsing**: Accepts comma-separated column names

### **Filter Hierarchy**

#### **BaseFilter** (Abstract Filter Template)

```php
abstract class BaseFilter implements Transformer {
    use HasLifecycleHooks;

    abstract protected function filter(Enumerable $dataset): Enumerable;

    public function transform(Enumerable $dataset): Enumerable {
        $this->runHooks('before-filter', $this, $dataset);
        $dataset = $this->filter($dataset);
        $this->runHooks('after-filter', $this, $dataset);
        return $dataset;
    }

    public function beforeFilter(callable $callback): self
    public function afterFilter(callable $callback): self
}
```

**Design Strengths**:
- ✅ **Transformer Compliance**: Implements Transformer interface
- ✅ **Hook Integration**: Built-in lifecycle events for filters
- ✅ **Template Method**: Consistent execution pattern
- ✅ **Fluent Interface**: Chainable hook configuration

#### **CriteriaFilter** (Complex Condition-Based Filtering)

```php
class CriteriaFilter extends BaseFilter {
    protected Builder $criteria;

    public function __construct(Builder $criteria)

    public static function make(array|Builder|\Closure|null $criteria = null): self {
        // Factory method for flexible construction
    }

    public function filter(Enumerable $dataset): Enumerable {
        return $dataset->filter([$this->criteria, 'evaluate']);
    }
}
```

**Design Excellence**:
- ✅ **Powerful Filtering**: Uses sophisticated condition builder
- ✅ **Factory Pattern**: Multiple construction methods
- ✅ **Integration**: Seamless connection to conditions system
- ✅ **Performance**: Efficient callback-based filtering

---

## 🛠️ **Utility Class Hierarchies**

### **Conditions System** (`src/Utils/Conditions/`)

#### **Builder Class** (Query Builder Pattern)

```php
class Builder {
    protected array $conditions = [];
    public array $operators = [/* ... */];

    // Factory
    public static function make(): static

    // Basic conditions
    public function where($field, $operator = null, $value = null, string $conjunction = 'and', bool $negate = false): static
    public function orWhere(/* ... */): static
    public function whereNot(/* ... */): static

    // Special conditions
    public function whereIn(string $field, array $values, /* ... */): static
    public function whereNull(string $field, /* ... */): static
    public function whereBetween(string $field, array $values, /* ... */): static

    // Column comparisons
    public function whereColumn(string $first, string $operator, $second = null, /* ... */): static

    // Nested conditions
    public function whereNested(\Closure $callback, /* ... */): static

    // Evaluation
    public function evaluate($row): bool
    public function build(): NestedCondition
}
```

**Design Excellence**:
- ✅ **Fluent Interface**: Laravel Query Builder inspired API
- ✅ **Comprehensive**: 40+ methods covering all condition types
- ✅ **Nested Support**: Complex grouped conditions
- ✅ **Type Safety**: Operator validation and value checking
- ✅ **Flexible**: Multiple ways to build same conditions

#### **Condition Classes**

```php
// Base condition (likely abstract)
class Condition {
    // Base functionality for all conditions
}

// Basic field conditions
class Where extends Condition {
    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $value,
        public readonly string $conjunction = 'and',
        public readonly bool $negate = false
    ) {}
}

// Column-to-column comparisons
class WhereColumn extends Condition {
    public function __construct(
        public readonly string $first,
        public readonly string $operator,
        public readonly mixed $second,
        public readonly string $conjunction = 'and',
        public readonly bool $negate = false
    ) {}
}

// Grouped conditions
class NestedCondition extends Condition {
    public function __construct(
        public readonly array $conditions,
        public readonly string $conjunction = 'and',
        public readonly bool $negate = false
    ) {}

    public function check($row): bool {
        // Recursive evaluation logic
    }
}
```

**Design Strengths**:
- ✅ **Immutable Objects**: Readonly properties prevent modification
- ✅ **Clear Hierarchy**: Inheritance provides common structure
- ✅ **Recursive Processing**: NestedCondition handles complex logic
- ✅ **Type Safety**: Constructor ensures valid condition state

---

## 🎭 **Facade Hierarchy**

### **Laravel Facade Integration**

```php
// Base facade structure
abstract class Facade {
    protected static function getFacadeAccessor(): string;
}

// Phetl facades
class Extract extends Facade {
    protected static function getFacadeAccessor(): string {
        return ExtractorBuilder::class;
    }
}

class Transform extends Facade {
    protected static function getFacadeAccessor(): string {
        return TransformationPipeline::class;
    }
}

class Load extends Facade {
    protected static function getFacadeAccessor(): string {
        return LoadBuilder::class; // Missing class!
    }
}

class Phetl extends Facade {
    protected static function getFacadeAccessor(): string {
        return Phetl::class; // Main Phetl class
    }
}
```

**Issues**:
- ❌ **Missing Target**: `Load` facade points to non-existent `LoadBuilder`
- ❌ **Circular Reference**: `Phetl` facade points to `Phetl` class

---

## 🔧 **Trait Hierarchy**

### **HasLifecycleHooks Trait**

```php
trait HasLifecycleHooks {
    protected array $hooks = [];

    // Hook management
    protected function addHook(string $event, callable $callback)
    protected function removeHook(string $event, callable $callback)
    public function clearHooks(string $hook)
    public function flushHooks()

    // Hook introspection
    public function hasHooks(string $event): bool
    public function getHooks(string $event): array
    public function getAllHooks(): array

    // Hook execution
    protected function runHooks(string $event, ...$args)
}
```

**Design Excellence**:
- ✅ **Complete Implementation**: All hook management functionality
- ✅ **Flexible**: Supports any number of hooks per event
- ✅ **Introspection**: Can query hook state
- ✅ **Parameter Passing**: Hooks receive context arguments
- ✅ **Memory Management**: Can clear/flush hooks

**Usage Across Classes**:
- `BaseExtractor` - Extraction lifecycle events
- `BaseFilter` - Filter lifecycle events
- `TransformationPipeline` - Pipeline lifecycle events

---

## 📊 **Class Relationship Matrix**

| Class | Implements | Extends | Uses Traits | Depends On |
|-------|------------|---------|-------------|------------|
| BaseExtractor | Extractor | - | HasLifecycleHooks | - |
| ApiExtractor | - | BaseExtractor | - | PendingRequest, Response |
| CsvExtractor | - | BaseExtractor | - | SimpleExcelReader |
| QueryExtractor | - | BaseExtractor | - | QueryBuilder, EloquentBuilder |
| RowTransformer | Transformer | - | - | - |
| ColumnTransformer | Transformer | - | - | - |
| BaseFilter | Transformer | - | HasLifecycleHooks | - |
| CriteriaFilter | - | BaseFilter | - | Builder |
| Builder | - | - | - | Where, WhereColumn, NestedCondition |
| TransformationPipeline | - | - | HasLifecycleHooks | Transformer |

---

## 🎯 **Inheritance Patterns Analysis**

### **Template Method Pattern Usage**

1. **BaseExtractor Template**:
   ```php
   run() {
       runHooks('before');
       data = extract(); // Subclass implements
       runHooks('after');
       return data;
   }
   ```

2. **RowTransformer Template**:
   ```php
   transform(dataset) {
       return dataset.map(transformRow); // Subclass implements
   }
   ```

3. **BaseFilter Template**:
   ```php
   transform(dataset) {
       runHooks('before');
       result = filter(dataset); // Subclass implements
       runHooks('after');
       return result;
   }
   ```

### **Strategy Pattern Implementation**

All major component hierarchies use strategy pattern:
- **Extractors**: Different data source strategies
- **Transformers**: Different transformation strategies
- **Filters**: Different filtering strategies
- **Conditions**: Different comparison strategies

### **Composition vs Inheritance**

**Good Use of Inheritance**:
- Abstract base classes provide common functionality
- Template methods define consistent algorithms
- Interface implementation ensures polymorphism

**Good Use of Composition**:
- Traits provide cross-cutting functionality (HasLifecycleHooks)
- Dependency injection for external libraries
- Builder classes compose different strategies

---

## 🚨 **Class Hierarchy Issues**

### **Critical Issues**

1. **AggregateExtractor**: Incomplete implementation
2. **Service Provider**: Broken class bindings
3. **Load Facade**: Points to missing LoadBuilder
4. **Phetl Class**: Empty implementation

### **Design Inconsistencies**

1. **Interface vs Implementation**:
   - `Extractor` interface defines `run()`
   - `BaseExtractor` adds `extract()` abstract method
   - Creates confusion about actual contract

2. **Visibility Issues**:
   - `TransformationPipeline::run()` is protected but should be public
   - Some methods lack proper return types

### **Missing Components**

1. **LoadBuilder Class**: Referenced but doesn't exist
2. **Loader Interface**: No interface for loading strategies
3. **Exception Hierarchy**: No custom exception classes

---

## 🛠️ **Recommended Improvements**

### **1. Fix Interface Consistency**
```php
// Option 1: Align interface with current implementation
interface Extractor {
    public function extract(): Enumerable; // Match BaseExtractor
    public function run(): Enumerable;
}

// Option 2: Simplify BaseExtractor (Recommended)
abstract class BaseExtractor implements Extractor {
    // Remove abstract extract(), use run() directly
    abstract public function run(): Enumerable;
}
```

### **2. Complete Missing Hierarchies**
```php
// Add Loader hierarchy
interface Loader {
    public function load(Enumerable $data): mixed;
}

abstract class BaseLoader implements Loader {
    use HasLifecycleHooks;
    // Common loader functionality
}

class DatabaseLoader extends BaseLoader { /* ... */ }
class FileLoader extends BaseLoader { /* ... */ }
class ApiLoader extends BaseLoader { /* ... */ }
```

### **3. Add Exception Hierarchy**
```php
interface EtlException extends Throwable {}

abstract class BaseEtlException extends Exception implements EtlException {
    // Common exception functionality
}

class ExtractionException extends BaseEtlException { /* ... */ }
class TransformationException extends BaseEtlException { /* ... */ }
class LoadException extends BaseEtlException { /* ... */ }
```

The class hierarchy shows excellent design patterns and SOLID principles implementation, with some critical gaps that need addressing for production readiness.

*Next: Read [Data Flow](05-data-flow.md) to understand how data moves through these class hierarchies.*