# Component Architecture - Phetl ETL Package

## 🏗️ **Component Overview**

Phetl is built as a modular system where each component has clear responsibilities and well-defined interfaces. This document details each major component and how they interact.

## 📊 **Component Dependency Graph**

```
┌─────────────────┐
│   Phetl Facade  │ (Entry Point)
└─────┬───────────┘
      │
      ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ ExtractorBuilder│    │TransformPipeline │    │   LoadBuilder   │
└─────┬───────────┘    └─────┬────────────┘    └─────┬───────────┘
      │                      │                       │
      ▼                      ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Extractors    │    │   Transformers   │    │    Loaders      │
└─────┬───────────┘    └─────┬────────────┘    └─────┬───────────┘
      │                      │                       │
      ▼                      ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ Lifecycle Hooks │    │   Conditions     │    │ Error Handling  │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

---

## 🎯 **Core Components**

### **1. Phetl Facade** (`src/Phetl.php`)

**Status**: ⚠️ *Needs Implementation*

**Purpose**: Main entry point and orchestrator for the entire ETL system

#### **Intended Design**
```php
class Phetl {
    public function extract(): ExtractorBuilder {
        return app(ExtractorBuilder::class);
    }

    public function transform(): TransformationPipeline {
        return app(TransformationPipeline::class);
    }

    public function load(): LoadBuilder {
        return app(LoadBuilder::class);
    }

    public function pipeline(): EtlProcessBuilder {
        return app(EtlProcessBuilder::class);
    }
}
```

#### **Usage Patterns**
```php
// Standalone operations
$data = Phetl::extract()->fromApi($url)->run();
$processed = Phetl::transform()->addField('processed_at', now())->run($data);

// Complete pipeline
Phetl::pipeline()
    ->extract(Extract::fromApi($url))
    ->transform($transformer1, $transformer2)
    ->load(Load::toDatabase('users'))
    ->run();
```

#### **Responsibilities**
- Provide unified API for all ETL operations
- Coordinate between different builders
- Handle service container integration
- Manage global configuration

---

### **2. Extraction Layer**

#### **ExtractorBuilder** (`src/Builders/ExtractorBuilder.php`)

**Status**: ✅ *Implemented*

**Purpose**: Factory for creating different types of extractors

```php
class ExtractorBuilder {
    public function fromApi($endpoint = null): ApiExtractor
    public function fromCsv(string $path = null): CsvExtractor
    public function fromQuery($query = null, $bindings = [], $connection = null): QueryExtractor
}
```

**Key Features**:
- Factory pattern for extractor creation
- Type-specific configuration methods
- Consistent interface across all extractor types

#### **BaseExtractor** (`src/Extractors/BaseExtractor.php`)

**Status**: ✅ *Well Implemented*

**Purpose**: Abstract base class providing common extractor functionality

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
}
```

**Key Features**:
- Template method pattern for consistent execution
- Lifecycle hook integration
- Common interface implementation
- Error handling foundation

#### **Concrete Extractors**

##### **ApiExtractor** (`src/Extractors/ApiExtractor.php`)

**Status**: ✅ *Well Implemented* (with minor bugs)

**Purpose**: Extract data from HTTP APIs

**Architecture**:
```php
class ApiExtractor extends BaseExtractor {
    protected PendingRequest $request;
    protected Response $response;
    protected \Closure $parser;
    protected ?string $data_path = null;

    // Fluent configuration methods
    public function parseBodyWith(callable $parser): self
    public function dataPath(string $key): self
    public function method($method): self
    public function onError(callable $callback): self
    public function lazy(): self
}
```

**Design Strengths**:
- Wraps Laravel HTTP client with adapter pattern
- Flexible response parsing (custom parser or JSON path)
- Error handling with custom callbacks
- Lazy collection support for memory efficiency
- Magic method forwarding to underlying HTTP client

**Current Issues**:
- Duplicate request execution in `sendRequest()`
- Error handler callback reference bug

##### **CsvExtractor** (`src/Extractors/CsvExtractor.php`)

**Status**: ✅ *Well Implemented*

**Purpose**: Extract data from CSV/Excel files

**Architecture**:
```php
class CsvExtractor extends BaseExtractor {
    protected SimpleExcelReader $reader;

    public function path(string $path): self
    public function __call($method, $args) // Forward to SimpleExcel
    public function extract(): Enumerable
}
```

**Design Strengths**:
- Adapter pattern wrapping Spatie SimpleExcel
- Magic method forwarding preserves library functionality
- Fluent interface maintained
- Lazy loading capability

##### **QueryExtractor** (`src/Extractors/QueryExtractor.php`)

**Status**: ✅ *Well Implemented*

**Purpose**: Extract data from database queries

**Architecture**:
```php
class QueryExtractor extends BaseExtractor {
    protected string|QueryBuilder|EloquentBuilder $query;
    protected array $bindings = [];
    protected ?string $connection = null;

    public function query($query): self
    public function raw(string $query, array $bindings = [], ?string $connection = null): self
}
```

**Design Strengths**:
- Supports multiple query types (raw SQL, Query Builder, Eloquent)
- Parameter binding for security
- Multiple database connection support
- Flexible construction via callable

##### **AggregateExtractor** (`src/Extractors/AggregateExtractor.php`)

**Status**: ⚠️ *Needs Implementation*

**Purpose**: Combine data from multiple extractors

**Intended Architecture**:
```php
class AggregateExtractor extends BaseExtractor {
    protected array $extractors = [];
    protected string $strategy = 'merge'; // merge, union, join

    public function add(Extractor $extractor): self
    public function merge(): self  // Combine all data
    public function union(): self  // Unique records only
    public function join(string $key): self // Join on common key
}
```

---

### **3. Transformation Layer**

#### **TransformationPipeline** (`src/TransformationPipeline.php`)

**Status**: ✅ *Core Implemented* (needs enhancement)

**Purpose**: Orchestrate multiple transformations in sequence

```php
class TransformationPipeline {
    use HasLifecycleHooks;

    protected array $transformers = [];

    public function addTransformer(callable|Transformer $transformer): self
    protected function run($data) // Should be public?
}
```

**Current Strengths**:
- Pipeline pattern implementation
- Lifecycle hooks integration
- Supports both callable and Transformer objects

**Enhancement Needed**:
- Make `run()` method public
- Add validation for transformer types
- Add error handling for failed transformations
- Add transformation metrics collection

#### **Transformer Hierarchy**

##### **Base Transformer Classes**

**RowTransformer** (`src/Transformers/RowTransformer.php`)
```php
abstract class RowTransformer implements Transformer {
    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map([$this, 'transformRow']);
    }
    abstract protected function transformRow($row);
}
```

**ColumnTransformer** (`src/Transformers/ColumnTransformer.php`)
```php
abstract class ColumnTransformer implements Transformer {
    private array|string $columns;

    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function ($row) {
            foreach ($this->columns as $column) {
                $row[$column] = $this->transformColumnValue($row[$column]);
            }
            return $row;
        });
    }
}
```

**Design Pattern**: Template Method - Base classes define algorithm, subclasses implement specific steps

##### **Filter System** (`src/Transformers/Filters/`)

**BaseFilter** - Abstract base for all filters
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
}
```

**CriteriaFilter** - Filter using condition builder
```php
class CriteriaFilter extends BaseFilter {
    protected Builder $criteria;

    public static function make(array|Builder|\Closure|null $criteria = null): self
    public function filter(Enumerable $dataset): Enumerable {
        return $dataset->filter([$this->criteria, 'evaluate']);
    }
}
```

**Filter Types**:
- `CriteriaFilter` - Complex condition-based filtering
- `CallbackFilter` - Simple function-based filtering
- `DuplicatesFilter` - Remove duplicate records
- `OffsetFilter` - Skip/take operations
- `ValidatorFilter` - Data validation filtering

---

### **4. Conditions System** (`src/Utils/Conditions/`)

**Status**: ✅ *Excellently Implemented*

**Purpose**: Sophisticated condition building and evaluation for filtering

#### **Architecture Overview**
```
Builder (Fluent Interface)
├── Where (Basic conditions)
├── WhereColumn (Column comparisons)
├── NestedCondition (Grouped conditions)
└── MakesComparisons (Evaluation logic)
```

#### **Builder Class** (`src/Utils/Conditions/Builder.php`)

**Design Excellence**:
- Fluent interface with 40+ condition methods
- Supports complex nested conditions
- Comprehensive operator support
- Laravel Query Builder inspired API

**Key Methods**:
```php
// Basic conditions
where($field, $operator, $value)
whereIn($field, array $values)
whereBetween($field, array $range)
whereNull($field)

// Column comparisons
whereColumn($first, $operator, $second)
whereColumnIn($first, array $columns)

// Nested conditions
where(function($builder) { /* ... */ })

// Evaluation
evaluate($row): bool
build(): NestedCondition
```

**Usage Example**:
```php
$criteria = Builder::make()
    ->where('status', 'active')
    ->where(function($builder) {
        $builder->where('type', 'premium')
                ->orWhere('credits', '>', 100);
    })
    ->whereNotNull('email');

$isValid = $criteria->evaluate($record);
```

---

### **5. Lifecycle Hooks System** (`src/Concerns/HasLifecycleHooks.php`)

**Status**: ✅ *Excellently Implemented*

**Purpose**: Event-driven architecture for cross-cutting concerns

#### **Implementation**
```php
trait HasLifecycleHooks {
    protected array $hooks = [];

    protected function addHook(string $event, callable $callback)
    protected function runHooks(string $event, ...$args)
    public function clearHooks(string $hook)
    public function flushHooks()
}
```

#### **Event Types**
```php
// Extraction Events
'before-extraction' => function(Extractor $extractor)
'after-extraction'  => function(Extractor $extractor, Enumerable $data)

// Transformation Events
'start-transformations' => function(TransformationPipeline $pipeline, $data)
'before-transformer'    => function(Transformer $transformer, $data)
'after-transformer'     => function(Transformer $transformer, $data)
'end-transformations'   => function(TransformationPipeline $pipeline, $data)

// Filter Events
'before-filter' => function(BaseFilter $filter, Enumerable $dataset)
'after-filter'  => function(BaseFilter $filter, Enumerable $dataset)
```

#### **Usage Patterns**
```php
// Logging
$extractor->afterExtraction(function($extractor, $data) {
    Log::info('Extracted records', ['count' => $data->count()]);
});

// Metrics
$transformer->beforeTransformer(function($transformer, $data) {
    Metrics::startTimer('transformation.' . get_class($transformer));
});

// Debugging
$pipeline->startTransformations(function($pipeline, $data) {
    dump('Starting transformations with ' . count($data) . ' records');
});
```

---

### **6. Service Provider Integration** (`src/PhetlServiceProvider.php`)

**Status**: ⚠️ *Has Critical Issues*

**Purpose**: Laravel service container integration and configuration

#### **Current Issues**
```php
// BROKEN - Missing imports and non-existent classes
$this->app->bind(ExtractorBuilder::class, function () {
    return new ExtractorBuilder; // Wrong namespace
});

$this->app->bind(LoaderBuilder::class, function () {
    return new LoaderBuilder; // Class doesn't exist
});
```

#### **Intended Architecture**
```php
class PhetlServiceProvider extends PackageServiceProvider {
    public function register() {
        // Bind builders
        $this->app->bind(ExtractorBuilder::class);
        $this->app->bind(TransformationPipeline::class);
        $this->app->bind(LoadBuilder::class);
        $this->app->bind(EtlProcessBuilder::class);

        // Bind main facade
        $this->app->bind(Phetl::class);
    }

    public function configurePackage(Package $package): void {
        $package->name('phetl')
                ->hasConfigFile()
                ->hasViews()
                ->hasMigration('create_phetl_table')
                ->hasCommand(PhetlCommand::class);
    }
}
```

---

## 🔄 **Component Interactions**

### **Data Flow Between Components**

1. **Extraction Phase**
   ```
   User Request → ExtractorBuilder → Concrete Extractor → BaseExtractor Template → Lifecycle Hooks → Data Collection
   ```

2. **Transformation Phase**
   ```
   Data Collection → TransformationPipeline → Individual Transformers → Conditions System → Processed Collection
   ```

3. **Loading Phase** (Planned)
   ```
   Processed Collection → LoadBuilder → Concrete Loader → Target System
   ```

### **Cross-Cutting Concerns**

#### **Error Handling Flow**
```
Component Error → Exception Hierarchy → Lifecycle Hooks → Logging → User Notification
```

#### **Configuration Flow**
```
Config Files → Service Provider → Component Builders → Runtime Configuration
```

#### **Monitoring Flow**
```
Component Events → Lifecycle Hooks → Metrics Collection → Logging System → Monitoring Dashboard
```

---

## 🏗️ **Component Design Principles**

### **1. Single Responsibility**
- Each component has one clear purpose
- Extractors only extract, transformers only transform
- Cross-cutting concerns handled by traits/hooks

### **2. Open/Closed Principle**
- Easy to add new extractors/transformers without modifying existing code
- Extension through inheritance and composition
- Plugin architecture ready for future expansion

### **3. Dependency Inversion**
- Components depend on interfaces, not concrete classes
- Service container manages dependencies
- Easy to swap implementations for testing

### **4. Interface Segregation**
- Small, focused interfaces (`Extractor`, `Transformer`, `Sequenceable`)
- Components only depend on interfaces they use
- No forced implementation of unused methods

### **5. Liskov Substitution**
- All extractors can be used interchangeably
- All transformers follow same contract
- Polymorphism works correctly throughout system

---

## 📊 **Component Maturity Matrix**

| Component | Implementation | Testing | Documentation | Stability |
|-----------|---------------|---------|---------------|-----------|
| ExtractorBuilder | ✅ Complete | ✅ Good | ⚠️ Partial | ✅ Stable |
| BaseExtractor | ✅ Complete | ✅ Good | ⚠️ Partial | ✅ Stable |
| ApiExtractor | ⚠️ Minor Bugs | ✅ Good | ⚠️ Partial | ⚠️ Needs Fix |
| CsvExtractor | ✅ Complete | ✅ Good | ⚠️ Partial | ✅ Stable |
| QueryExtractor | ✅ Complete | ✅ Good | ⚠️ Partial | ✅ Stable |
| AggregateExtractor | ❌ Empty | ❌ None | ❌ None | ❌ Broken |
| TransformationPipeline | ⚠️ Partial | ⚠️ Limited | ⚠️ Partial | ⚠️ Needs Work |
| Transformers | ✅ Core Done | ✅ Good | ⚠️ Partial | ✅ Stable |
| Conditions System | ✅ Excellent | ✅ Excellent | ⚠️ Partial | ✅ Stable |
| Lifecycle Hooks | ✅ Excellent | ✅ Good | ⚠️ Partial | ✅ Stable |
| Service Provider | ❌ Broken | ❌ Failing | ⚠️ Partial | ❌ Broken |
| Phetl Facade | ❌ Empty | ❌ None | ❌ None | ❌ Missing |
| Load Layer | ❌ Missing | ❌ None | ❌ None | ❌ Missing |

**Legend**: ✅ Good, ⚠️ Needs Work, ❌ Critical Issue

---

## 🎯 **Component Roadmap**

### **Phase 1: Fix Critical Issues**
1. Fix service provider bindings
2. Implement Phetl main facade
3. Fix AggregateExtractor implementation
4. Fix ApiExtractor bugs

### **Phase 2: Complete Core Architecture**
1. Implement Load layer (LoadBuilder, Loaders)
2. Complete EtlProcessBuilder
3. Enhance TransformationPipeline
4. Add comprehensive error handling

### **Phase 3: Advanced Features**
1. Add streaming support to all extractors
2. Implement caching layer
3. Add validation system
4. Create monitoring dashboard

### **Phase 4: Polish & Optimization**
1. Performance optimization
2. Memory management improvements
3. Advanced configuration options
4. Plugin architecture

---

## 🔍 **Component Testing Strategy**

### **Unit Testing**
- Each component tested in isolation
- Mock dependencies using interfaces
- Test both success and failure scenarios

### **Integration Testing**
- Test component interactions
- End-to-end pipeline testing
- Performance benchmarking

### **Architecture Testing**
- Dependency direction enforcement
- Interface compliance verification
- SOLID principle adherence

The component architecture provides a solid foundation for a powerful, flexible ETL system while maintaining Laravel conventions and design excellence.

*Next: Read [Class Hierarchies](04-class-hierarchies.md) to understand the detailed class relationships and inheritance patterns.*