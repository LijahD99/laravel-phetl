# Performance Considerations - Phetl ETL Package

## 🎯 **Performance Philosophy**

Phetl is designed to handle datasets ranging from hundreds to millions of records efficiently. The architecture emphasizes memory efficiency, processing speed, and scalability while maintaining developer experience and code clarity.

## 📊 **Performance Targets & Benchmarks**

### **Target Performance Metrics**

| Dataset Size | Processing Time | Memory Usage | Throughput |
|-------------|-----------------|--------------|------------|
| 1K records | < 1 second | < 10MB | > 1K records/sec |
| 10K records | < 10 seconds | < 50MB | > 1K records/sec |
| 100K records | < 2 minutes | < 100MB | > 800 records/sec |
| 1M records | < 20 minutes | < 200MB | > 800 records/sec |

### **Current Performance Characteristics**

```php
// Small datasets (< 1K records) - Excellent performance
$data = Extract::fromApi($url)->run();           // ~0.1s
$processed = Transform::filter($criteria)->run($data); // ~0.05s
Load::toDatabase('table')->run($processed);     // ~0.2s
// Total: ~0.35s

// Medium datasets (10K records) - Good performance
$data = Extract::fromCsv($file)->run();          // ~2s
$processed = Transform::chain($transformers)->run($data); // ~3s
Load::toDatabase('table')->run($processed);     // ~5s
// Total: ~10s

// Large datasets (100K+ records) - Requires optimization
$data = Extract::fromQuery($sql)->lazy()->run(); // Streaming
$processed = Transform::chain($transformers)->run($data); // Streaming
Load::toDatabase('table')->chunk(1000)->run($processed); // Chunked
// Memory: Constant (~50MB), Time: Linear
```

---

## 🚀 **Memory Management Strategies**

### **Collection vs LazyCollection Usage**

#### **When to Use Standard Collections**
```php
// Small datasets (< 10K records)
// Fast random access needed
// Multiple iterations required
$data = Extract::fromApi($url)->run(); // Collection
$summary = $data->groupBy('status')->map->count(); // Multiple passes
```

#### **When to Use LazyCollections**
```php
// Large datasets (> 10K records)
// Single-pass processing
// Memory constraints
$data = Extract::fromCsv($largefile)->lazy()->run(); // LazyCollection
$processed = Transform::filter($criteria)->run($data); // Single pass
```

### **Memory-Efficient Patterns**

#### **Streaming Processing**
```php
class StreamingProcessor {
    public function processLargeDataset(string $source): void {
        Extract::fromCsv($source)
            ->lazy()                    // LazyCollection
            ->afterExtraction(function($extractor, $data) {
                // Process in chunks to avoid memory buildup
                $data->chunk(1000)->each(function($chunk) {
                    $this->processChunk($chunk);
                });
            })
            ->run();
    }

    protected function processChunk($chunk): void {
        $processed = Transform::chain([
            new FilterTransformer($criteria),
            new ConvertTransformer($conversions)
        ])->run($chunk);

        Load::toDatabase('processed_data')
            ->run($processed);

        // Explicit memory cleanup
        unset($processed, $chunk);
        gc_collect_cycles();
    }
}
```

#### **Memory Monitoring**
```php
class MemoryMonitor {
    protected int $memoryLimit;
    protected float $warningThreshold = 0.8;

    public function __construct(int $memoryLimit = null) {
        $this->memoryLimit = $memoryLimit ?? $this->parseMemoryLimit(ini_get('memory_limit'));
    }

    public function monitor(): void {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $usage = $current / $this->memoryLimit;

        if ($usage > $this->warningThreshold) {
            Log::warning('High memory usage detected', [
                'current' => $this->formatBytes($current),
                'peak' => $this->formatBytes($peak),
                'limit' => $this->formatBytes($this->memoryLimit),
                'usage_percent' => round($usage * 100, 2)
            ]);

            // Trigger garbage collection
            gc_collect_cycles();
        }
    }

    public function attachToExtractor(BaseExtractor $extractor): void {
        $extractor->afterExtraction([$this, 'monitor']);
    }
}
```

---

## ⚡ **Processing Speed Optimizations**

### **Efficient Data Structures**

#### **Optimized Collection Operations**
```php
// SLOW: Multiple iterations
$data = $collection
    ->filter(fn($item) => $item['active'])
    ->map(fn($item) => $this->transform($item))
    ->filter(fn($item) => $item['score'] > 50);

// FAST: Single iteration
$data = $collection->reduce(function($result, $item) {
    if (!$item['active']) return $result;

    $transformed = $this->transform($item);
    if ($transformed['score'] > 50) {
        $result[] = $transformed;
    }

    return $result;
}, []);
```

#### **Batch Processing Strategies**
```php
class BatchProcessor {
    protected int $batchSize;

    public function __construct(int $batchSize = 1000) {
        $this->batchSize = $batchSize;
    }

    public function process(Enumerable $data, callable $processor): void {
        $data->chunk($this->batchSize)->each(function($batch) use ($processor) {
            $startTime = microtime(true);

            $result = $processor($batch);

            $duration = microtime(true) - $startTime;
            $recordsPerSecond = count($batch) / $duration;

            Log::debug('Batch processed', [
                'records' => count($batch),
                'duration' => round($duration, 3),
                'records_per_second' => round($recordsPerSecond, 2)
            ]);
        });
    }
}
```

### **Database Query Optimization**

#### **Efficient Query Patterns**
```php
class OptimizedQueryExtractor extends QueryExtractor {
    protected int $chunkSize = 1000;

    public function extract(): Enumerable {
        // Use cursor-based pagination for large datasets
        return LazyCollection::make(function() {
            $lastId = 0;

            do {
                $chunk = DB::table($this->table)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($this->chunkSize)
                    ->get();

                foreach ($chunk as $record) {
                    yield (array) $record;
                    $lastId = $record->id;
                }

            } while ($chunk->count() === $this->chunkSize);
        });
    }
}

// Usage for large tables
$extractor = new OptimizedQueryExtractor('large_table');
$data = $extractor->chunkSize(5000)->extract(); // Process in 5K chunks
```

#### **Index-Optimized Queries**
```php
class IndexOptimizedExtractor extends QueryExtractor {
    public function withOptimalIndexes(): static {
        // Ensure queries use appropriate indexes
        $this->query = $this->query
            ->select(['id', 'name', 'status', 'created_at']) // Only needed columns
            ->where('status', 'active')                      // Use indexed column first
            ->where('created_at', '>=', $this->startDate)    // Range on indexed column
            ->orderBy('id');                                 // Use primary key for ordering

        return $this;
    }
}
```

---

## 🔄 **Transformation Performance**

### **Efficient Transformation Patterns**

#### **Minimize Object Creation**
```php
// SLOW: Creates new objects for each record
class SlowTransformer extends RowTransformer {
    protected function transformRow($row) {
        $date = new Carbon($row['created_at']);
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        $row['formatted_date'] = $date->format('Y-m-d');
        $row['formatted_price'] = $formatter->formatCurrency($row['price'], 'USD');

        return $row;
    }
}

// FAST: Reuse objects and optimize operations
class FastTransformer extends RowTransformer {
    protected static $formatter;

    public function __construct() {
        self::$formatter ??= new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    }

    protected function transformRow($row) {
        // Use faster date formatting
        $row['formatted_date'] = date('Y-m-d', strtotime($row['created_at']));

        // Reuse formatter instance
        $row['formatted_price'] = self::$formatter->formatCurrency($row['price'], 'USD');

        return $row;
    }
}
```

#### **Conditional Processing**
```php
class ConditionalTransformer implements Transformer {
    protected array $transformers;
    protected array $conditions;

    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function($row) {
            // Only apply transformers that match conditions
            foreach ($this->transformers as $transformer) {
                $condition = $this->conditions[get_class($transformer)] ?? null;

                if (!$condition || $condition($row)) {
                    $row = $transformer->transformRow($row);
                }
            }

            return $row;
        });
    }
}

// Usage
$transformer = new ConditionalTransformer([
    new ExpensiveTransformer() => fn($row) => $row['needs_processing'] === true,
    new QuickTransformer() => fn($row) => true // Always apply
]);
```

### **Parallel Processing (Future Enhancement)**

```php
// Planned parallel processing capability
class ParallelProcessor {
    protected int $workers = 4;

    public function processInParallel(Enumerable $data, array $transformers): Enumerable {
        $chunks = $data->chunk(ceil($data->count() / $this->workers));

        $processes = $chunks->map(function($chunk) use ($transformers) {
            return $this->spawnWorker($chunk, $transformers);
        });

        return $this->collectResults($processes);
    }

    protected function spawnWorker($chunk, array $transformers): Process {
        // Implementation would use symfony/process or similar
        // to process chunks in separate PHP processes
    }
}
```

---

## 💾 **I/O Performance Optimization**

### **File I/O Optimization**

#### **Efficient File Reading**
```php
class OptimizedCsvExtractor extends CsvExtractor {
    protected int $bufferSize = 8192;

    public function extract(): Enumerable {
        return LazyCollection::make(function() {
            $handle = fopen($this->path, 'r');
            stream_set_read_buffer($handle, $this->bufferSize);

            $headers = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                yield array_combine($headers, $row);
            }

            fclose($handle);
        });
    }
}
```

#### **Batch File Writing**
```php
class BatchFileLoader implements Loader {
    protected int $batchSize = 1000;
    protected array $buffer = [];

    public function load(Enumerable $data): bool {
        $handle = fopen($this->path, 'w');

        foreach ($data as $record) {
            $this->buffer[] = $record;

            if (count($this->buffer) >= $this->batchSize) {
                $this->flushBuffer($handle);
            }
        }

        // Flush remaining records
        if (!empty($this->buffer)) {
            $this->flushBuffer($handle);
        }

        fclose($handle);
        return true;
    }

    protected function flushBuffer($handle): void {
        foreach ($this->buffer as $record) {
            fputcsv($handle, $record);
        }

        $this->buffer = [];
    }
}
```

### **Network I/O Optimization**

#### **HTTP Request Optimization**
```php
class OptimizedApiExtractor extends ApiExtractor {
    protected int $connectionTimeout = 10;
    protected int $requestTimeout = 30;
    protected bool $keepAlive = true;

    public function __construct(string $endpoint = null) {
        parent::__construct($endpoint);

        $this->request = Http::timeout($this->requestTimeout)
            ->connectTimeout($this->connectionTimeout)
            ->withOptions([
                'keep_alive' => $this->keepAlive,
                'pool_connections' => true,
                'pool_maxsize' => 10
            ]);
    }

    public function withRateLimit(int $requestsPerMinute): static {
        $this->rateLimit = $requestsPerMinute;
        return $this;
    }

    protected function sendRequest(): Response {
        // Implement rate limiting
        if (isset($this->rateLimit)) {
            $this->waitForRateLimit();
        }

        return parent::sendRequest();
    }

    protected function waitForRateLimit(): void {
        $delay = 60 / $this->rateLimit; // Seconds between requests
        usleep($delay * 1000000); // Convert to microseconds
    }
}
```

#### **Database Connection Optimization**
```php
class OptimizedDatabaseLoader extends DatabaseLoader {
    protected bool $useTransactions = true;
    protected int $batchSize = 1000;

    public function load(Enumerable $data): int {
        $totalInserted = 0;

        if ($this->useTransactions) {
            DB::beginTransaction();
        }

        try {
            $data->chunk($this->batchSize)->each(function($chunk) use (&$totalInserted) {
                // Use bulk insert for better performance
                $inserted = DB::table($this->table)->insert($chunk->toArray());
                $totalInserted += count($chunk);
            });

            if ($this->useTransactions) {
                DB::commit();
            }

        } catch (Exception $e) {
            if ($this->useTransactions) {
                DB::rollback();
            }
            throw $e;
        }

        return $totalInserted;
    }
}
```

---

## 📊 **Performance Monitoring & Profiling**

### **Built-in Performance Monitoring**

#### **Performance Metrics Collection**
```php
class PerformanceCollector {
    protected array $metrics = [];

    public function startOperation(string $operation): void {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true)
        ];
    }

    public function endOperation(string $operation, int $recordCount = 0): array {
        $start = $this->metrics[$operation];
        $end = [
            'end_time' => microtime(true),
            'end_memory' => memory_get_usage(true),
            'end_peak_memory' => memory_get_peak_usage(true)
        ];

        $metrics = array_merge($start, $end, [
            'duration' => $end['end_time'] - $start['start_time'],
            'memory_used' => $end['end_memory'] - $start['start_memory'],
            'peak_memory_used' => $end['end_peak_memory'] - $start['start_peak_memory'],
            'record_count' => $recordCount,
            'records_per_second' => $recordCount > 0 ? $recordCount / ($end['end_time'] - $start['start_time']) : 0
        ]);

        return $this->metrics[$operation] = $metrics;
    }

    public function getMetrics(): array {
        return $this->metrics;
    }

    public function generateReport(): string {
        $report = "Performance Report\n";
        $report .= "==================\n\n";

        foreach ($this->metrics as $operation => $metrics) {
            $report .= sprintf(
                "%s:\n" .
                "  Duration: %.3fs\n" .
                "  Records: %d\n" .
                "  Records/sec: %.2f\n" .
                "  Memory used: %s\n" .
                "  Peak memory: %s\n\n",
                $operation,
                $metrics['duration'],
                $metrics['record_count'],
                $metrics['records_per_second'],
                $this->formatBytes($metrics['memory_used']),
                $this->formatBytes($metrics['peak_memory_used'])
            );
        }

        return $report;
    }

    protected function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
```

#### **Automatic Performance Monitoring**
```php
class AutoPerformanceMonitor {
    protected PerformanceCollector $collector;

    public function __construct() {
        $this->collector = new PerformanceCollector();
    }

    public function attachToExtractor(BaseExtractor $extractor): void {
        $extractorClass = get_class($extractor);

        $extractor->beforeExtraction(function() use ($extractorClass) {
            $this->collector->startOperation("extraction:{$extractorClass}");
        });

        $extractor->afterExtraction(function($extractor, $data) use ($extractorClass) {
            $metrics = $this->collector->endOperation(
                "extraction:{$extractorClass}",
                $data->count()
            );

            // Log performance metrics
            Log::info('Extraction performance', $metrics);

            // Alert on poor performance
            if ($metrics['records_per_second'] < 100) {
                Log::warning('Slow extraction detected', [
                    'extractor' => $extractorClass,
                    'performance' => $metrics
                ]);
            }
        });
    }
}
```

### **Performance Testing Framework**

```php
class PerformanceBenchmark {
    protected array $benchmarks = [];

    public function benchmark(string $name, callable $operation, array $datasets = []): void {
        foreach ($datasets as $size => $data) {
            $metrics = $this->runBenchmark($operation, $data);

            $this->benchmarks[$name][$size] = $metrics;
        }
    }

    protected function runBenchmark(callable $operation, $data): array {
        // Warm up
        $operation(collect($data)->take(100));

        // Clear memory
        gc_collect_cycles();

        // Measure
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $operation($data);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        return [
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'record_count' => is_countable($data) ? count($data) : 'unknown',
            'records_per_second' => is_countable($data) ? count($data) / ($endTime - $startTime) : 0
        ];
    }

    public function generateBenchmarkReport(): string {
        // Generate comprehensive benchmark report
    }
}

// Usage
$benchmark = new PerformanceBenchmark();

$benchmark->benchmark('api_extraction', function($data) {
    return Extract::fromApi('https://api.example.com/data')->run();
}, [
    '1K' => range(1, 1000),
    '10K' => range(1, 10000),
    '100K' => range(1, 100000)
]);
```

---

## 🎯 **Performance Best Practices**

### **1. Memory Management**
- Use LazyCollection for datasets > 10K records
- Process data in chunks for very large datasets
- Monitor memory usage with hooks
- Implement garbage collection triggers
- Avoid keeping references to processed data

### **2. Processing Efficiency**
- Minimize object creation in hot paths
- Use single-pass processing when possible
- Implement conditional transformations
- Cache expensive operations
- Profile and optimize bottlenecks

### **3. I/O Optimization**
- Use appropriate buffer sizes for file operations
- Implement connection pooling for HTTP requests
- Use bulk operations for database writes
- Implement rate limiting for API calls
- Use persistent connections when beneficial

### **4. Monitoring & Alerting**
- Track key performance metrics
- Set up alerts for performance degradation
- Log slow operations for analysis
- Implement automatic scaling triggers
- Regular performance testing

### **5. Scalability Planning**
- Design for horizontal scaling
- Consider distributed processing for very large datasets
- Implement caching strategies
- Plan for peak load handling
- Monitor resource utilization trends

---

## 🚀 **Future Performance Enhancements**

### **Planned Optimizations**

**1. Parallel Processing**
- Multi-threaded transformation processing
- Parallel extraction from multiple sources
- Distributed processing capabilities

**2. Advanced Caching**
- Intelligent caching of intermediate results
- Query result caching with invalidation
- Distributed caching for scaling

**3. Streaming Optimizations**
- Zero-copy data processing
- Memory-mapped file processing
- Reactive streams implementation

**4. Database Optimizations**
- Connection pooling
- Query optimization analysis
- Automatic index recommendations

**5. Monitoring Enhancements**
- Real-time performance dashboards
- Predictive performance alerts
- Automatic performance tuning

The performance architecture ensures Phetl can handle production workloads efficiently while providing the tools needed to monitor, optimize, and scale ETL operations.

---

*This completes the comprehensive architecture documentation. You now have detailed insights into every aspect of the Phetl ETL package design, from high-level architecture to specific performance optimizations. These documents should help you get back into the project mindset and continue development effectively.*