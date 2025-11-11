# System Overview - Phetl ETL Package

## 🎯 **Vision & Philosophy**

Phetl is designed as a **Laravel-native ETL (Extract, Transform, Load) package** that prioritizes:

1. **Developer Experience** - Fluent, intuitive API that feels natural to Laravel developers
2. **Flexibility** - Pluggable architecture allowing custom extractors, transformers, and loaders
3. **Performance** - Efficient processing of large datasets with streaming and chunking support
4. **Reliability** - Comprehensive error handling, validation, and monitoring capabilities
5. **Laravel Integration** - Deep integration with Laravel's ecosystem (Collections, HTTP client, etc.)

## 🏗️ **High-Level Architecture**

### **Core Concept: The ETL Pipeline**

```
┌─────────────┐    ┌──────────────┐    ┌─────────────┐
│   EXTRACT   │───▶│  TRANSFORM   │───▶│    LOAD     │
│             │    │              │    │             │
│ • API       │    │ • Filters    │    │ • Database  │
│ • CSV/Excel │    │ • Converters │    │ • Files     │
│ • Database  │    │ • Mappers    │    │ • APIs      │
│ • Custom    │    │ • Custom     │    │ • Custom    │
└─────────────┘    └──────────────┘    └─────────────┘
```

### **Three-Layer Architecture**

1. **Extraction Layer** - Pulls data from various sources
2. **Transformation Layer** - Processes, cleans, and reshapes data
3. **Loading Layer** - Writes data to target destinations

Each layer is:
- **Independent** - Can be used standalone or combined
- **Extensible** - Easy to add new implementations
- **Testable** - Clear interfaces for mocking and testing
- **Configurable** - Rich configuration options for different use cases

## 🔧 **Core Components**

### **1. Extractors** (`src/Extractors/`)
**Purpose**: Pull data from various sources into standardized Laravel Collections

**Key Classes**:
- `BaseExtractor` - Abstract base with lifecycle hooks
- `ApiExtractor` - HTTP API data extraction
- `CsvExtractor` - CSV/Excel file extraction
- `QueryExtractor` - Database query extraction
- `AggregateExtractor` - Combines multiple extractors

**Design Principle**: Each extractor implements a common interface but handles the specifics of its data source.

### **2. Transformers** (`src/Transformers/`)
**Purpose**: Process and modify data flowing through the pipeline

**Categories**:
- **Row Transformers** - Process entire rows/records
- **Column Transformers** - Process specific columns/fields
- **Filters** - Remove unwanted data based on criteria
- **Converters** - Change data types and formats

**Design Principle**: Composable transformations that can be chained together.

### **3. Loaders** (`src/Builders/LoadBuilder.php` - *To be implemented*)
**Purpose**: Write processed data to target destinations

**Planned Types**:
- `DatabaseLoader` - Insert/update database records
- `FileLoader` - Write to CSV, JSON, Excel files
- `ApiLoader` - POST/PUT to HTTP APIs
- `CollectionLoader` - Return as Laravel Collection

### **4. Pipeline Orchestration**
**Purpose**: Coordinate the entire ETL process

**Key Classes**:
- `EtlProcessBuilder` - Fluent API for building pipelines
- `TransformationPipeline` - Manages transformation chains
- `Phetl` - Main facade and entry point

## 🎨 **Design Philosophy**

### **Laravel-First Approach**
- Uses Laravel Collections as the primary data structure
- Integrates with Laravel's HTTP client, database, and caching
- Follows Laravel naming conventions and patterns
- Works seamlessly with Laravel's service container

### **Fluent Interface Design**
```php
// Your vision for the API
Phetl::extract()
    ->fromApi('https://api.example.com/users')
    ->acceptJson()
    ->dataPath('data')
    ->transform()
    ->filter(['status' => 'active'])
    ->convert('created_at')->toDateTime()
    ->addField('processed_at', now())
    ->load()
    ->toDatabase('users')
    ->run();
```

### **Event-Driven Architecture**
- Lifecycle hooks at every stage (before/after extraction, transformation, loading)
- Observer pattern for monitoring and logging
- Extensible event system for custom behaviors

### **Separation of Concerns**
- Each class has a single, well-defined responsibility
- Clear interfaces between components
- Minimal coupling between layers

## 🔄 **Data Flow Architecture**

### **Typical Processing Flow**

1. **Configuration Phase**
   ```php
   $pipeline = Phetl::pipeline()
       ->extract(Extract::fromApi($url))
       ->transform($transformer1, $transformer2)
       ->load(Load::toDatabase('table'));
   ```

2. **Execution Phase**
   ```php
   $result = $pipeline->run();
   ```

3. **Internal Flow**
   ```
   Source Data → Extractor → Collection → Transformer Chain → Collection → Loader → Result
   ```

### **Data Structure Standardization**
- All extractors return `Illuminate\Support\Enumerable` (Collection or LazyCollection)
- Consistent array/object structure within collections
- Transformers maintain the Enumerable contract
- Loaders accept Enumerable input

## 🏛️ **Architectural Patterns**

### **Primary Patterns**

1. **Strategy Pattern** - Pluggable extractors, transformers, loaders
2. **Builder Pattern** - Fluent API construction (ExtractorBuilder, LoadBuilder)
3. **Pipeline Pattern** - Sequential data processing
4. **Facade Pattern** - Simple interface over complex subsystems
5. **Observer Pattern** - Lifecycle hooks and event handling

### **Supporting Patterns**

1. **Factory Pattern** - Creating appropriate extractor/transformer instances
2. **Template Method** - BaseExtractor defines extraction workflow
3. **Chain of Responsibility** - Transformation pipeline processing
4. **Adapter Pattern** - Integrating third-party libraries (SimpleExcel, HTTP Client)

## 🔌 **Extension Architecture**

### **Extension Points**

1. **Custom Extractors** - Implement `Extractor` interface
2. **Custom Transformers** - Implement `Transformer` interface
3. **Custom Loaders** - Implement `Loader` interface (when created)
4. **Custom Conditions** - Extend conditions system for filtering
5. **Custom Hooks** - Add lifecycle event handlers

### **Plugin Architecture** (Future)
- Package-based extensions
- Auto-discovery of custom implementations
- Configuration-driven component selection

## 📊 **Performance Architecture**

### **Current Optimizations**
- LazyCollection support for memory efficiency
- Streaming APIs for large datasets
- Efficient iteration patterns

### **Planned Optimizations**
- Chunked processing for very large datasets
- Connection pooling for database operations
- Caching layer for repeated operations
- Parallel processing for independent transformations

## 🛡️ **Error Handling Architecture**

### **Exception Hierarchy** (Planned)
```
EtlException (interface)
├── ExtractionException
├── TransformationException
├── LoadException
└── ValidationException
```

### **Error Recovery**
- Graceful degradation for non-critical errors
- Retry mechanisms for transient failures
- Circuit breaker pattern for external dependencies
- Comprehensive logging for debugging

## 🧪 **Testing Architecture**

### **Testing Strategy**
- **Unit Tests** - Individual class functionality
- **Integration Tests** - Component interaction
- **End-to-End Tests** - Complete pipeline execution
- **Performance Tests** - Benchmarking and memory usage

### **Test Structure**
```
tests/
├── Unit/
│   ├── Extractors/
│   ├── Transformers/
│   └── Utils/
├── Integration/
│   └── Pipelines/
├── Performance/
│   └── Benchmarks/
└── TestCase.php
```

## 🔮 **Future Architecture Considerations**

### **Scalability Enhancements**
- Distributed processing support
- Message queue integration for async processing
- Horizontal scaling capabilities

### **Enterprise Features**
- Pipeline versioning and rollback
- Data lineage tracking
- Comprehensive audit logging
- Role-based access control

### **Cloud Integration**
- Support for cloud storage (S3, GCS, Azure Blob)
- Integration with cloud databases
- Serverless execution support

## 📝 **Key Architectural Decisions**

### **Why Laravel Collections?**
- **Familiarity** - Laravel developers already know the API
- **Performance** - Optimized for PHP data processing
- **Functionality** - Rich set of data manipulation methods
- **Consistency** - Single data structure throughout pipeline

### **Why Separate Builders?**
- **Single Responsibility** - Each builder focuses on one concern
- **Fluent API** - Enables method chaining and readable code
- **Extensibility** - Easy to add new configuration options
- **Testing** - Easier to test individual builders

### **Why Lifecycle Hooks?**
- **Observability** - Essential for monitoring ETL processes
- **Extensibility** - Allows custom behavior injection
- **Debugging** - Helps with troubleshooting data issues
- **Integration** - Enables logging, metrics, and alerting

## 🎯 **Success Metrics**

### **Developer Experience**
- Time to first working pipeline: < 10 minutes
- Lines of code for common tasks: < 20 lines
- Learning curve: Familiar to Laravel developers

### **Performance**
- Memory efficiency: < 100MB for 10K records
- Processing speed: > 1K records/second
- Scalability: Handle up to 1M records

### **Reliability**
- Error recovery: Graceful handling of common failures
- Data integrity: No data loss during processing
- Monitoring: Comprehensive logging and metrics

---

This architecture is designed to grow with your needs while maintaining simplicity and Laravel conventions. The modular design ensures that you can start simple and add complexity only where needed.

*Next: Read [Design Patterns](02-design-patterns.md) to understand the specific patterns used throughout the system.*