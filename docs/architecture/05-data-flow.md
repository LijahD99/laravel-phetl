# Data Flow - Phetl ETL Package

## 🌊 **Data Flow Philosophy**

Phetl's data flow is designed around Laravel Collections as the universal data format, ensuring consistency, performance, and familiar developer experience throughout the entire ETL pipeline.

## 📊 **Master Data Flow Diagram**

```
┌─────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   SOURCES   │    │   PROCESSING     │    │  DESTINATIONS   │
│             │    │                  │    │                 │
│ • APIs      │───▶│ Extract          │───▶│ • Database      │
│ • Files     │    │    ↓             │    │ • Files         │
│ • Database  │    │ Transform        │    │ • APIs          │
│ • Custom    │    │    ↓             │    │ • Memory        │
│             │    │ Load             │    │                 │
└─────────────┘    └──────────────────┘    └─────────────────┘

        Raw Data → Collections → Processed Collections → Final Output
```

## 🔄 **Detailed Data Flow Stages**

### **Stage 1: Data Extraction**

#### **Source Data Types**
```php
// Various input formats
API Response    → JSON/XML/Array
CSV/Excel File  → Tabular Data
Database Query  → Result Set
Custom Source   → Any Format
```

#### **Extraction Process**
```
1. Configuration Phase
   User Request → ExtractorBuilder → Concrete Extractor

2. Preparation Phase
   Extractor Setup → Connection/Authentication → Ready State

3. Lifecycle Hook Phase
   before-extraction hooks → Context: (Extractor)

4. Data Retrieval Phase
   Source System → Raw Data → Extractor Processing

5. Standardization Phase
   Raw Data → Laravel Collection/LazyCollection

6. Lifecycle Hook Phase
   after-extraction hooks → Context: (Extractor, Collection)

7. Output Phase
   Standardized Collection → Next Stage
```

#### **Data Standardization Examples**

**API Extraction Flow**:
```php
// Input: HTTP Response
{
  "data": {
    "users": [
      {"id": 1, "name": "John", "active": true},
      {"id": 2, "name": "Jane", "active": false}
    ]
  },
  "meta": {"total": 2}
}

// Processing: ApiExtractor
$extractor = Extract::fromApi('https://api.example.com/users')
    ->dataPath('data.users'); // Focus on specific data

// Output: Laravel Collection
collect([
    ["id" => 1, "name" => "John", "active" => true],
    ["id" => 2, "name" => "Jane", "active" => false]
])
```

**CSV Extraction Flow**:
```php
// Input: CSV File
id,name,active
1,John,true
2,Jane,false

// Processing: CsvExtractor
$extractor = Extract::fromCsv('users.csv')
    ->useHeaders(['id', 'name', 'active']);

// Output: Laravel Collection (same format as API)
collect([
    ["id" => "1", "name" => "John", "active" => "true"],
    ["id" => "2", "name" => "Jane", "active" => "false"]
])
```

**Database Extraction Flow**:
```php
// Input: Database Query
SELECT id, name, active FROM users WHERE active = 1

// Processing: QueryExtractor
$extractor = Extract::fromQuery('SELECT id, name, active FROM users WHERE active = ?', [1]);

// Output: Laravel Collection (objects converted to arrays)
collect([
    ["id" => 1, "name" => "John", "active" => 1],
    ["id" => 2, "name" => "Jane", "active" => 0]
])
```

### **Stage 2: Data Transformation**

#### **Transformation Pipeline Flow**
```
Input Collection
      ↓
┌─────────────────┐
│ Pipeline Start  │ → before-pipeline hooks
└─────────────────┘
      ↓
┌─────────────────┐
│ Transformer 1   │ → before-transformer hooks
│                 │ → transform(collection)
│                 │ → after-transformer hooks
└─────────────────┘
      ↓
┌─────────────────┐
│ Transformer 2   │ → before-transformer hooks
│                 │ → transform(collection)
│                 │ → after-transformer hooks
└─────────────────┘
      ↓
┌─────────────────┐
│ Transformer N   │ → before-transformer hooks
│                 │ → transform(collection)
│                 │ → after-transformer hooks
└─────────────────┘
      ↓
┌─────────────────┐
│ Pipeline End    │ → after-pipeline hooks
└─────────────────┘
      ↓
Output Collection
```

#### **Transformation Types and Data Flow**

**Row Transformations** (Each record processed individually):
```php
// Input Collection
collect([
    ["name" => "john", "created_at" => "2023-01-01"],
    ["name" => "jane", "created_at" => "2023-01-02"]
])

// RowTransformer: Capitalize names
class CapitalizeNameTransformer extends RowTransformer {
    protected function transformRow($row) {
        $row['name'] = ucfirst($row['name']);
        return $row;
    }
}

// Output Collection
collect([
    ["name" => "John", "created_at" => "2023-01-01"],
    ["name" => "Jane", "created_at" => "2023-01-02"]
])
```

**Column Transformations** (Specific fields processed):
```php
// ColumnTransformer: Convert dates
class DateColumnTransformer extends ColumnTransformer {
    public function __construct() {
        parent::__construct(['created_at', 'updated_at']);
    }

    protected function transformColumnValue($value) {
        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}

// Input: "2023-01-01" → Output: "2023-01-01 00:00:00"
```

**Filter Transformations** (Remove unwanted data):
```php
// Filter: Active users only
$filter = CriteriaFilter::make(
    Builder::make()->where('active', true)
);

// Input Collection (2 records)
collect([
    ["name" => "John", "active" => true],
    ["name" => "Jane", "active" => false]
])

// Output Collection (1 record)
collect([
    ["name" => "John", "active" => true]
])
```

**Aggregation Transformations** (Combine/summarize data):
```php
// Group by status and count
$aggregator = new GroupByTransformer('status', 'count');

// Input Collection
collect([
    ["name" => "John", "status" => "active"],
    ["name" => "Jane", "status" => "active"],
    ["name" => "Bob", "status" => "inactive"]
])

// Output Collection
collect([
    ["status" => "active", "count" => 2],
    ["status" => "inactive", "count" => 1]
])
```

### **Stage 3: Data Loading** (Planned Implementation)

#### **Loading Process Flow**
```
Input Collection
      ↓
┌─────────────────┐
│ LoadBuilder     │ → Determine target type
└─────────────────┘
      ↓
┌─────────────────┐
│ Concrete Loader │ → before-load hooks
│                 │ → Prepare target
│                 │ → Write data
│                 │ → after-load hooks
└─────────────────┘
      ↓
Result/Confirmation
```

#### **Planned Loader Data Flows**

**Database Loading**:
```php
// Input: Processed Collection
collect([
    ["name" => "John", "email" => "john@example.com"],
    ["name" => "Jane", "email" => "jane@example.com"]
])

// DatabaseLoader Process
1. Begin transaction
2. Prepare insert/upsert statements
3. Batch insert records
4. Commit transaction
5. Return affected row count

// Output: int (number of affected rows)
```

**File Loading**:
```php
// Input: Processed Collection (same as above)

// FileLoader Process
1. Open file handle
2. Write headers (if CSV)
3. Write each record
4. Close file handle
5. Return success boolean

// Output: bool (success/failure)
```

**API Loading**:
```php
// Input: Processed Collection (same as above)

// ApiLoader Process
1. Chunk data if needed
2. Prepare HTTP request
3. Send data to API
4. Handle response/errors
5. Return API response

// Output: Response object
```

---

## 🔧 **Data Structure Standards**

### **Collection Structure Convention**

Throughout Phetl, data follows consistent structure:

```php
// Standard Record Format
[
    "field_name" => "field_value",
    "another_field" => "another_value",
    // ... more fields
]

// Collection Format
collect([
    ["id" => 1, "name" => "John"],    // Record 1
    ["id" => 2, "name" => "Jane"],    // Record 2
    // ... more records
])
```

### **Data Type Handling**

**Primitive Types**:
```php
// String values
"name" => "John Doe"

// Numeric values
"age" => 25
"salary" => 50000.00

// Boolean values
"active" => true

// Null values
"middle_name" => null

// Date/Time (as strings initially)
"created_at" => "2023-01-01 10:30:00"
```

**Complex Types**:
```php
// Arrays (for complex fields)
"tags" => ["php", "laravel", "etl"]

// Objects (converted to arrays)
"address" => [
    "street" => "123 Main St",
    "city" => "Springfield",
    "state" => "IL"
]

// JSON (as strings, can be parsed by transformers)
"metadata" => '{"source": "api", "processed": true}'
```

---

## 💾 **Memory Management & Performance**

### **Collection vs LazyCollection**

**Standard Collection Flow** (Eager Loading):
```php
Source → Full Dataset Load → Memory → Processing → Output
```
- ✅ Fast random access
- ✅ Multiple iterations efficient
- ⚠️ High memory usage for large datasets

**LazyCollection Flow** (Streaming):
```php
Source → Chunk Loading → Processing → Chunk Output → Next Chunk
```
- ✅ Low memory usage
- ✅ Handles unlimited data size
- ⚠️ Single-pass processing only

### **Memory Flow Examples**

**Small Dataset (< 1000 records)**:
```php
$data = Extract::fromApi($url)->run(); // Collection
$processed = $pipeline->run($data);    // Collection
Load::toDatabase($processed);          // Collection
```

**Large Dataset (> 10,000 records)**:
```php
$data = Extract::fromApi($url)->lazy()->run(); // LazyCollection
$processed = $pipeline->run($data);             // LazyCollection
Load::toDatabase($processed)->chunk(1000);     // Chunked processing
```

### **Streaming Data Flow**

```
┌─────────────┐    ┌──────────────┐    ┌─────────────┐
│   Source    │    │  Processing  │    │   Target    │
│             │    │              │    │             │
│ ┌─────────┐ │    │ ┌──────────┐ │    │ ┌─────────┐ │
│ │ Chunk 1 │─│───▶│ │Transform │─│───▶│ │ Write 1 │ │
│ └─────────┘ │    │ └──────────┘ │    │ └─────────┘ │
│ ┌─────────┐ │    │ ┌──────────┐ │    │ ┌─────────┐ │
│ │ Chunk 2 │─│───▶│ │Transform │─│───▶│ │ Write 2 │ │
│ └─────────┘ │    │ └──────────┘ │    │ └─────────┘ │
│     ...     │    │     ...      │    │     ...     │
└─────────────┘    └──────────────┘    └─────────────┘
```

---

## 🔍 **Data Flow Monitoring & Debugging**

### **Lifecycle Hook Data Flow**

**Hook Events and Data Access**:
```php
$extractor->beforeExtraction(function($extractor) {
    // Access: Extractor configuration
    Log::info('Starting extraction', [
        'type' => get_class($extractor),
        'config' => $extractor->getConfig()
    ]);
});

$extractor->afterExtraction(function($extractor, $data) {
    // Access: Extractor + extracted data
    Log::info('Extraction complete', [
        'type' => get_class($extractor),
        'record_count' => $data->count(),
        'memory_usage' => memory_get_usage(true)
    ]);
});

$transformer->beforeTransformer(function($transformer, $data) {
    // Access: Transformer + input data
    Log::debug('Transform start', [
        'transformer' => get_class($transformer),
        'input_count' => $data->count()
    ]);
});

$transformer->afterTransformer(function($transformer, $data) {
    // Access: Transformer + output data
    Log::debug('Transform end', [
        'transformer' => get_class($transformer),
        'output_count' => $data->count()
    ]);
});
```

### **Data Inspection Points**

```php
// At each stage, data can be inspected
$pipeline = Phetl::pipeline()
    ->extract($extractor)
    ->transform(
        // Add inspection transformer
        new CallbackTransformer(function($data) {
            dump('After extraction:', $data->take(5)); // Show first 5
            return $data;
        }),

        $actualTransformer,

        // Add another inspection point
        new CallbackTransformer(function($data) {
            dump('After transformation:', $data->take(5));
            return $data;
        })
    )
    ->load($loader);
```

---

## 🚨 **Error Handling in Data Flow**

### **Error Propagation**

```
Data Flow: Source → Extract → Transform → Load → Target
Error Flow: Exception ← Exception ← Exception ← Exception ← Error
```

### **Error Context Preservation**

```php
try {
    $result = $pipeline->run();
} catch (ExtractionException $e) {
    // Access to:
    // - Original exception
    // - Extractor that failed
    // - Configuration that caused failure
    // - Partial data (if any)

} catch (TransformationException $e) {
    // Access to:
    // - Original exception
    // - Transformer that failed
    // - Input data that caused failure
    // - Pipeline state

} catch (LoadException $e) {
    // Access to:
    // - Original exception
    // - Loader that failed
    // - Data that couldn't be loaded
    // - Target system error details
}
```

---

## 📊 **Data Flow Performance Metrics**

### **Key Performance Indicators**

**Throughput Metrics**:
- Records per second processed
- Memory usage per record
- Time per transformation step
- I/O wait times

**Quality Metrics**:
- Data loss percentage
- Transformation accuracy
- Error rate by stage
- Recovery success rate

### **Performance Monitoring Flow**

```php
class PerformanceMonitor {
    public function beforeExtraction($extractor) {
        $this->startTimer('extraction');
        $this->recordMemory('extraction_start');
    }

    public function afterExtraction($extractor, $data) {
        $this->stopTimer('extraction');
        $this->recordMemory('extraction_end');
        $this->recordMetric('records_extracted', $data->count());
    }

    public function beforeTransformation($transformer, $data) {
        $this->startTimer('transformation_' . get_class($transformer));
        $this->recordMetric('input_records', $data->count());
    }

    public function afterTransformation($transformer, $data) {
        $this->stopTimer('transformation_' . get_class($transformer));
        $this->recordMetric('output_records', $data->count());
    }
}
```

---

## 🎯 **Data Flow Best Practices**

### **1. Consistent Data Structure**
- Always use associative arrays for records
- Maintain consistent field naming conventions
- Handle null values gracefully

### **2. Memory Efficiency**
- Use LazyCollection for large datasets
- Process data in chunks when possible
- Clean up resources in lifecycle hooks

### **3. Error Resilience**
- Validate data at each stage boundary
- Provide meaningful error context
- Implement graceful degradation

### **4. Monitoring & Observability**
- Log key metrics at each stage
- Use lifecycle hooks for monitoring
- Track data quality metrics

### **5. Performance Optimization**
- Profile memory usage patterns
- Optimize transformation algorithms
- Use appropriate collection types

---

## 🔮 **Future Data Flow Enhancements**

### **Planned Features**

**1. Parallel Processing**:
```php
// Process chunks in parallel
$pipeline->parallel()
    ->chunks(1000)
    ->workers(4)
    ->run();
```

**2. Data Validation**:
```php
// Validate at each stage
$pipeline->validate($schema)
    ->extract($extractor)
    ->validate($transformedSchema)
    ->transform($transformer)
    ->validate($finalSchema)
    ->load($loader);
```

**3. Data Lineage Tracking**:
```php
// Track data origin and transformations
$lineage = $pipeline->withLineage()
    ->run()
    ->getLineage();
```

**4. Caching Layer**:
```php
// Cache intermediate results
$pipeline->cache('extraction', 3600)
    ->extract($extractor)
    ->cache('transformation', 1800)
    ->transform($transformer)
    ->load($loader);
```

The data flow architecture ensures consistent, performant, and observable data processing throughout the entire ETL pipeline while maintaining Laravel's elegant simplicity.

*Next: Read [Extension Points](06-extension-points.md) to learn how to customize and extend the data flow for your specific needs.*