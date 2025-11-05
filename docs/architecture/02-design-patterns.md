# Design Patterns - Phetl ETL Package

## 🎯 **Pattern Selection Philosophy**

The patterns chosen for Phetl prioritize:
1. **Familiarity** to Laravel developers
2. **Flexibility** for different use cases
3. **Maintainability** of the codebase
4. **Extensibility** for future features

## 🏗️ **Primary Architectural Patterns**

### **1. Strategy Pattern** 🎯

**Where Used**: Extractors, Transformers, Loaders, Filters

**Purpose**: Allow different algorithms/implementations to be used interchangeably

#### **Implementation Example - Extractors**
```php
// Strategy Interface
interface Extractor {
    public function run(): Enumerable;
}

// Concrete Strategies
class ApiExtractor implements Extractor { /* ... */ }
class CsvExtractor implements Extractor { /* ... */ }
class QueryExtractor implements Extractor { /* ... */ }

// Context (ExtractorBuilder) can use any strategy
$extractor = Extract::fromApi($url);    // ApiExtractor strategy
$extractor = Extract::fromCsv($path);   // CsvExtractor strategy
$extractor = Extract::fromQuery($sql);  // QueryExtractor strategy
```

#### **Why This Pattern Works Here**
- **Polymorphism**: All extractors can be used identically
- **Open/Closed**: Easy to add new extractor types without changing existing code
- **Single Responsibility**: Each extractor focuses on one data source type

#### **Current Implementations**
```
Extractors (Strategy Family):
├── BaseExtractor (Abstract Strategy)
├── ApiExtractor (HTTP APIs)
├── CsvExtractor (CSV/Excel files)
├── QueryExtractor (Database queries)
└── AggregateExtractor (Multiple sources)

Transformers (Strategy Family):
├── RowTransformer (Process entire rows)
├── ColumnTransformer (Process specific columns)
├── BaseFilter (Filter data)
└── Various Converters (Type conversions)
```

---

### **2. Builder Pattern** 🔧

**Where Used**: ExtractorBuilder, LoadBuilder, EtlProcessBuilder

**Purpose**: Construct complex objects step by step with a fluent interface

#### **Implementation Example - ExtractorBuilder**
```php
class ExtractorBuilder {
    public function fromApi($endpoint = null): ApiExtractor {
        return new ApiExtractor($endpoint);
    }

    public function fromCsv(string $path = null): CsvExtractor {
        return new CsvExtractor($path);
    }

    public function fromQuery($query = null): QueryExtractor {
        return new QueryExtractor($query);
    }
}

// Usage - fluent construction
$extractor = Extract::fromApi('https://api.example.com/users')
    ->acceptJson()
    ->withHeaders(['Authorization' => 'Bearer token'])
    ->dataPath('data.users')
    ->beforeExtraction($callback);
```

#### **Why This Pattern Works Here**
- **Fluent Interface**: Natural, readable configuration
- **Step-by-step Construction**: Complex objects built incrementally
- **Optional Parameters**: Many configuration options, not all required
- **Laravel Convention**: Matches Laravel's query builder style

#### **Advanced Builder - EtlProcessBuilder**
```php
// Your intended design
class EtlProcessBuilder {
    private ?Extractor $extractor = null;
    private array $transformers = [];
    private ?Loader $loader = null;

    public function extract(Extractor $extractor): self {
        $this->extractor = $extractor;
        return $this;
    }

    public function transform(Transformer ...$transformers): self {
        $this->transformers = array_merge($this->transformers, $transformers);
        return $this;
    }

    public function load(Loader $loader): self {
        $this->loader = $loader;
        return $this;
    }

    public function run(): mixed {
        // Orchestrate the entire pipeline
    }
}
```

---

### **3. Pipeline Pattern** 🔄

**Where Used**: TransformationPipeline, EtlProcessBuilder

**Purpose**: Process data through a sequence of operations

#### **Implementation Example - TransformationPipeline**
```php
class TransformationPipeline {
    protected array $transformers = [];

    public function addTransformer(callable|Transformer $transformer): self {
        $this->transformers[] = $transformer;
        return $this;
    }

    protected function run($data): mixed {
        foreach ($this->transformers as $transformer) {
            $data = $transformer->transform($data);
        }
        return $data;
    }
}
```

#### **Pipeline Composition**
```php
// Linear pipeline
Extract → Transform → Transform → Transform → Load

// With lifecycle hooks
Extract → [hooks] → Transform → [hooks] → Transform → [hooks] → Load
```

#### **Why This Pattern Works Here**
- **Sequential Processing**: Data flows through transformations in order
- **Composability**: Can combine different transformations
- **Reusability**: Same transformations can be used in different pipelines
- **Debugging**: Easy to inspect data at each stage

---

### **4. Facade Pattern** 🎭

**Where Used**: Extract, Transform, Load, Phetl facades

**Purpose**: Provide a simple interface to complex subsystems

#### **Implementation Example**
```php
// Facade provides simple interface
class Extract extends Facade {
    protected static function getFacadeAccessor(): string {
        return ExtractorBuilder::class;
    }
}

// Usage - simple interface hides complexity
$data = Extract::fromApi($url)->run();

// Instead of:
$builder = app(ExtractorBuilder::class);
$extractor = $builder->fromApi($url);
$data = $extractor->run();
```

#### **Facade Hierarchy**
```
Laravel Facades:
├── Extract → ExtractorBuilder
├── Transform → TransformationPipeline
├── Load → LoadBuilder
└── Phetl → Phetl (main orchestrator)
```

#### **Why This Pattern Works Here**
- **Laravel Convention**: Matches Laravel's facade system
- **Simplicity**: Reduces boilerplate for common operations
- **Discoverability**: Static methods are IDE-friendly
- **Testability**: Facades can be mocked easily

---

### **5. Observer Pattern** 👁️

**Where Used**: HasLifecycleHooks trait, event system

**Purpose**: Define dependencies between objects without tight coupling

#### **Implementation Example - Lifecycle Hooks**
```php
trait HasLifecycleHooks {
    protected array $hooks = [];

    protected function addHook(string $event, callable $callback): void {
        $this->hooks[$event][] = $callback;
    }

    protected function runHooks(string $event, ...$args): void {
        foreach ($this->hooks[$event] ?? [] as $callback) {
            call_user_func($callback, ...$args);
        }
    }
}

// Usage
$extractor->beforeExtraction(function($extractor) {
    Log::info('Starting extraction from ' . $extractor->getSource());
});

$extractor->afterExtraction(function($extractor, $data) {
    Log::info('Extracted ' . $data->count() . ' records');
});
```

#### **Event Flow**
```
Extraction Events:
├── before-extraction
├── after-extraction
├── extraction-failed

Transformation Events:
├── start-transformations
├── before-transformer
├── after-transformer
├── end-transformations

Loading Events (planned):
├── before-load
├── after-load
├── load-failed
```

#### **Why This Pattern Works Here**
- **Decoupling**: Core logic separated from cross-cutting concerns
- **Extensibility**: Easy to add monitoring, logging, caching
- **Event-Driven**: Natural fit for ETL process monitoring
- **Debugging**: Hooks provide visibility into process flow

---

## 🔧 **Supporting Design Patterns**

### **6. Template Method Pattern** 📋

**Where Used**: BaseExtractor, BaseFilter

**Purpose**: Define algorithm skeleton, let subclasses implement specific steps

#### **Implementation Example**
```php
abstract class BaseExtractor implements Extractor {
    // Template method - defines the algorithm
    public function run(): Enumerable {
        $this->runHooks('before-extraction', $this);

        $data = $this->extract(); // Subclass implements this

        $this->runHooks('after-extraction', $this, $data);

        return $data;
    }

    // Abstract method - subclasses must implement
    abstract public function extract(): Enumerable;
}
```

#### **Why Used**
- **Code Reuse**: Common workflow in base class
- **Consistency**: All extractors follow same pattern
- **Hook Integration**: Lifecycle hooks built into template

---

### **7. Adapter Pattern** 🔌

**Where Used**: CsvExtractor (wraps SimpleExcel), ApiExtractor (wraps HTTP Client)

**Purpose**: Make incompatible interfaces work together

#### **Implementation Example - CsvExtractor**
```php
class CsvExtractor extends BaseExtractor {
    protected SimpleExcelReader $reader;

    // Adapter methods - forward to wrapped library
    public function __call($method, $args) {
        if (!method_exists($this->reader, $method)) {
            throw new \BadMethodCallException(/*...*/);
        }

        $this->reader->{$method}(...$args);
        return $this; // Maintain fluent interface
    }

    // Implementation specific to our interface
    public function extract(): Enumerable {
        return $this->reader->getRows(); // Adapt to our return type
    }
}
```

#### **Why Used**
- **Third-party Integration**: Leverage existing libraries
- **Interface Consistency**: All extractors have same interface
- **Feature Preservation**: Don't lose library functionality

---

### **8. Chain of Responsibility** 🔗

**Where Used**: Transformation pipeline, condition evaluation

**Purpose**: Pass requests along a chain of handlers

#### **Implementation Example - Transformations**
```php
// Each transformer is a handler in the chain
class TransformationPipeline {
    protected function run($data) {
        foreach ($this->transformers as $transformer) {
            // Each transformer handles the data and passes it on
            $data = $transformer->transform($data);
        }
        return $data;
    }
}
```

#### **Condition Chain Example**
```php
// Complex condition evaluation
Builder::make()
    ->where('status', 'active')           // Handler 1
    ->where('created_at', '>', '2023-01-01') // Handler 2
    ->whereIn('type', ['premium', 'pro'])    // Handler 3
    ->evaluate($record);
```

---

### **9. Factory Pattern** 🏭

**Where Used**: Builder classes, condition creation

**Purpose**: Create objects without specifying exact classes

#### **Implementation Example**
```php
class ExtractorBuilder {
    // Factory methods
    public function fromApi($endpoint = null): ApiExtractor {
        return new ApiExtractor($endpoint);
    }

    public function fromCsv(string $path = null): CsvExtractor {
        return new CsvExtractor($path);
    }
}

// Usage
$extractor = Extract::fromApi($url);      // Factory creates ApiExtractor
$extractor = Extract::fromCsv($path);     // Factory creates CsvExtractor
```

---

## 🏛️ **Pattern Interactions**

### **How Patterns Work Together**

1. **Strategy + Builder**: Builders create different strategies
   ```php
   Extract::fromApi($url)    // Builder creates ApiExtractor strategy
   ```

2. **Pipeline + Observer**: Pipeline triggers events at each stage
   ```php
   foreach ($transformers as $transformer) {
       $this->runHooks('before-transformer', $transformer, $data);
       $data = $transformer->transform($data);
       $this->runHooks('after-transformer', $transformer, $data);
   }
   ```

3. **Template Method + Observer**: Template integrates hooks
   ```php
   public function run(): Enumerable {
       $this->runHooks('before-extraction', $this);  // Observer
       $data = $this->extract();                     // Template Method
       $this->runHooks('after-extraction', $this, $data); // Observer
       return $data;
   }
   ```

4. **Facade + Factory**: Facades delegate to factory builders
   ```php
   // Facade → Builder (Factory) → Strategy
   Extract::fromApi($url) // Facade calls ExtractorBuilder factory
   ```

### **Pattern Benefits Matrix**

| Pattern | Flexibility | Maintainability | Performance | Laravel Integration |
|---------|-------------|-----------------|-------------|-------------------|
| Strategy | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| Builder | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Pipeline | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| Facade | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Observer | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

## 🚀 **Pattern Evolution**

### **Current State**
- Core patterns implemented: Strategy, Builder, Observer, Facade
- Template Method partially implemented
- Pipeline pattern in TransformationPipeline

### **Planned Enhancements**
- **Command Pattern**: For undoable operations and queuing
- **Decorator Pattern**: For adding features to existing extractors/loaders
- **Composite Pattern**: For complex data structures and nested operations
- **State Pattern**: For managing pipeline execution states

### **Anti-Patterns Avoided**
- **God Object**: Large classes doing everything (separated into focused components)
- **Singleton**: Avoided in favor of Laravel's service container
- **Anemic Domain Model**: Rich objects with behavior, not just data
- **Tight Coupling**: Interfaces and dependency injection used throughout

## 📚 **Pattern Resources**

### **Further Reading**
- Gang of Four Design Patterns book
- Laravel's use of design patterns
- Martin Fowler's Enterprise Application Patterns

### **Laravel Pattern Examples**
- **Facade**: `Route`, `DB`, `Cache`
- **Builder**: Query Builder, Route Groups
- **Observer**: Event System, Model Events
- **Strategy**: Cache Drivers, Queue Drivers
- **Pipeline**: Middleware Pipeline

## 🎯 **Key Takeaways**

1. **Patterns Serve Purpose**: Each pattern solves specific problems in ETL processing
2. **Laravel Integration**: Patterns chosen to feel natural to Laravel developers
3. **Composition Over Inheritance**: Multiple patterns work together effectively
4. **Flexibility**: Architecture supports extension without modification
5. **Maintainability**: Clear separation of concerns and responsibilities

The pattern selection creates an architecture that is both powerful and familiar, allowing for complex ETL operations while maintaining Laravel's elegant simplicity.

*Next: Read [Component Architecture](03-component-architecture.md) to see how these patterns are applied to specific components.*