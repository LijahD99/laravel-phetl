# Extension Points - Phetl ETL Package

## 🔌 **Extension Philosophy**

Phetl is designed to be highly extensible while maintaining simplicity. The architecture provides multiple extension points that follow SOLID principles, allowing you to customize behavior without modifying core code.

## 🎯 **Extension Strategy Overview**

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Core Framework  │    │ Extension APIs  │    │ Custom Impl.    │
│                 │    │                 │    │                 │
│ • Interfaces    │───▶│ • Abstract Base │───▶│ • Your Classes  │
│ • Base Classes  │    │ • Traits        │    │ • Plugins       │
│ • Hooks System  │    │ • Builders      │    │ • Configurations│
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🏗️ **Primary Extension Points**

### **1. Custom Extractors** 🔍

**Extension Interface**: `Windsor\Phetl\Contracts\Extractor`

#### **Minimal Implementation**
```php
use Windsor\Phetl\Contracts\Extractor;
use Illuminate\Support\Enumerable;

class CustomExtractor implements Extractor {
    public function run(): Enumerable {
        // Your extraction logic
        $data = $this->getDataFromSource();
        return collect($data);
    }
}
```

#### **Recommended Implementation** (Using BaseExtractor)
```php
use Windsor\Phetl\Extractors\BaseExtractor;
use Illuminate\Support\Enumerable;

class CustomExtractor extends BaseExtractor {
    protected string $source;
    protected array $options;

    public function __construct(string $source, array $options = []) {
        $this->source = $source;
        $this->options = $options;
    }

    public function extract(): Enumerable {
        // Lifecycle hooks automatically handled by BaseExtractor

        // Your specific extraction logic
        $rawData = $this->connectToSource($this->source);
        $processedData = $this->processRawData($rawData);

        return collect($processedData);
    }

    // Fluent configuration methods
    public function withOption(string $key, mixed $value): static {
        $this->options[$key] = $value;
        return $this;
    }

    protected function connectToSource(string $source) {
        // Implementation specific to your data source
    }

    protected function processRawData($raw) {
        // Convert to standard format
    }
}
```

#### **Real-World Examples**

**MongoDB Extractor**:
```php
class MongoExtractor extends BaseExtractor {
    protected string $collection;
    protected array $filter = [];
    protected array $options = [];

    public function collection(string $name): static {
        $this->collection = $name;
        return $this;
    }

    public function filter(array $filter): static {
        $this->filter = $filter;
        return $this;
    }

    public function extract(): Enumerable {
        $client = new MongoDB\Client(config('database.connections.mongodb.dsn'));
        $collection = $client->selectCollection(
            config('database.connections.mongodb.database'),
            $this->collection
        );

        $cursor = $collection->find($this->filter, $this->options);

        return LazyCollection::make(function() use ($cursor) {
            foreach ($cursor as $document) {
                yield $document->toArray();
            }
        });
    }
}
```

**Redis Extractor**:
```php
class RedisExtractor extends BaseExtractor {
    protected string $pattern = '*';
    protected string $connection = 'default';

    public function pattern(string $pattern): static {
        $this->pattern = $pattern;
        return $this;
    }

    public function connection(string $connection): static {
        $this->connection = $connection;
        return $this;
    }

    public function extract(): Enumerable {
        $redis = Redis::connection($this->connection);
        $keys = $redis->keys($this->pattern);

        return collect($keys)->map(function($key) use ($redis) {
            return [
                'key' => $key,
                'value' => $redis->get($key),
                'type' => $redis->type($key),
                'ttl' => $redis->ttl($key)
            ];
        });
    }
}
```

**S3 Bucket Extractor**:
```php
class S3Extractor extends BaseExtractor {
    protected string $bucket;
    protected string $prefix = '';

    public function bucket(string $bucket): static {
        $this->bucket = $bucket;
        return $this;
    }

    public function prefix(string $prefix): static {
        $this->prefix = $prefix;
        return $this;
    }

    public function extract(): Enumerable {
        $s3 = Storage::disk('s3');
        $files = $s3->files($this->prefix);

        return collect($files)->map(function($file) use ($s3) {
            return [
                'path' => $file,
                'size' => $s3->size($file),
                'last_modified' => $s3->lastModified($file),
                'content' => $s3->get($file) // Be careful with large files
            ];
        });
    }
}
```

#### **Registering Custom Extractors**

**Option 1: Direct Usage**
```php
$extractor = new CustomExtractor($source, $options);
$data = $extractor->run();
```

**Option 2: Via ExtractorBuilder Extension**
```php
// Extend ExtractorBuilder
class ExtendedExtractorBuilder extends ExtractorBuilder {
    public function fromMongo(string $collection): MongoExtractor {
        return new MongoExtractor($collection);
    }

    public function fromRedis(string $pattern = '*'): RedisExtractor {
        return (new RedisExtractor())->pattern($pattern);
    }

    public function fromS3(string $bucket): S3Extractor {
        return (new S3Extractor())->bucket($bucket);
    }
}

// Register in service provider
$this->app->bind(ExtractorBuilder::class, ExtendedExtractorBuilder::class);

// Usage
$data = Extract::fromMongo('users')->filter(['active' => true])->run();
```

---

### **2. Custom Transformers** 🔄

**Extension Interface**: `Windsor\Phetl\Contracts\Transformer`

#### **Base Transformer Types**

**Row-Level Transformer** (Process entire records):
```php
use Windsor\Phetl\Transformers\RowTransformer;

class CustomRowTransformer extends RowTransformer {
    protected function transformRow($row) {
        // Transform the entire row
        $row['processed_at'] = now()->toISOString();
        $row['full_name'] = $row['first_name'] . ' ' . $row['last_name'];
        unset($row['first_name'], $row['last_name']);

        return $row;
    }
}
```

**Column-Level Transformer** (Process specific fields):
```php
use Windsor\Phetl\Transformers\ColumnTransformer;

class PhoneNumberFormatter extends ColumnTransformer {
    public function __construct() {
        parent::__construct(['phone', 'mobile', 'home_phone']);
    }

    protected function transformColumnValue($value) {
        // Format phone numbers
        $cleaned = preg_replace('/[^0-9]/', '', $value);

        if (strlen($cleaned) === 10) {
            return sprintf('(%s) %s-%s',
                substr($cleaned, 0, 3),
                substr($cleaned, 3, 3),
                substr($cleaned, 6, 4)
            );
        }

        return $value; // Return original if can't format
    }
}
```

**Direct Transformer Implementation**:
```php
use Windsor\Phetl\Contracts\Transformer;
use Illuminate\Support\Enumerable;

class DataEnrichmentTransformer implements Transformer {
    protected array $enrichmentRules;

    public function __construct(array $rules) {
        $this->enrichmentRules = $rules;
    }

    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function($row) {
            foreach ($this->enrichmentRules as $field => $rule) {
                $row[$field] = $this->applyRule($row, $rule);
            }
            return $row;
        });
    }

    protected function applyRule(array $row, callable $rule) {
        return $rule($row);
    }
}

// Usage
$enricher = new DataEnrichmentTransformer([
    'risk_score' => function($row) {
        return $this->calculateRiskScore($row);
    },
    'category' => function($row) {
        return $this->categorizeRecord($row);
    }
]);
```

#### **Advanced Transformer Examples**

**Machine Learning Transformer**:
```php
class MLPredictionTransformer implements Transformer {
    protected $model;
    protected array $features;
    protected string $predictionField;

    public function __construct($model, array $features, string $predictionField = 'prediction') {
        $this->model = $model;
        $this->features = $features;
        $this->predictionField = $predictionField;
    }

    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function($row) {
            $featureVector = collect($this->features)
                ->map(fn($feature) => $row[$feature] ?? 0)
                ->values()
                ->toArray();

            $prediction = $this->model->predict($featureVector);
            $row[$this->predictionField] = $prediction;

            return $row;
        });
    }
}
```

**Geocoding Transformer**:
```php
class GeocodingTransformer extends ColumnTransformer {
    protected $geocoder;

    public function __construct(array $addressColumns = ['address']) {
        parent::__construct($addressColumns);
        $this->geocoder = app(GeocoderInterface::class);
    }

    protected function transformColumnValue($address) {
        try {
            $result = $this->geocoder->geocode($address)->first();

            return [
                'original' => $address,
                'formatted' => $result->getFormattedAddress(),
                'latitude' => $result->getLatitude(),
                'longitude' => $result->getLongitude(),
                'country' => $result->getCountry()?->getName()
            ];
        } catch (Exception $e) {
            return ['original' => $address, 'error' => $e->getMessage()];
        }
    }
}
```

**Data Validation Transformer**:
```php
class ValidationTransformer implements Transformer {
    protected array $rules;
    protected bool $strict;

    public function __construct(array $rules, bool $strict = false) {
        $this->rules = $rules;
        $this->strict = $strict;
    }

    public function transform(Enumerable $dataset): Enumerable {
        return $dataset->map(function($row) {
            $validator = Validator::make($row, $this->rules);

            if ($validator->fails()) {
                if ($this->strict) {
                    throw new ValidationException(
                        'Validation failed for record',
                        $validator->errors()->toArray()
                    );
                }

                $row['_validation_errors'] = $validator->errors()->toArray();
            } else {
                $row['_validated'] = true;
            }

            return $row;
        });
    }
}
```

---

### **3. Custom Filters** 🔍

**Extension Base**: `Windsor\Phetl\Transformers\Filters\BaseFilter`

#### **Simple Custom Filter**
```php
use Windsor\Phetl\Transformers\Filters\BaseFilter;
use Illuminate\Support\Enumerable;

class BusinessHoursFilter extends BaseFilter {
    protected string $dateField;
    protected string $timezone;

    public function __construct(string $dateField = 'created_at', string $timezone = 'UTC') {
        $this->dateField = $dateField;
        $this->timezone = $timezone;
    }

    protected function filter(Enumerable $dataset): Enumerable {
        return $dataset->filter(function($row) {
            $date = Carbon::parse($row[$this->dateField])->setTimezone($this->timezone);
            $hour = $date->hour;

            // Filter to business hours (9 AM - 5 PM)
            return $hour >= 9 && $hour < 17 && $date->isWeekday();
        });
    }
}
```

#### **Advanced Filter with Configuration**
```php
class GeographicFilter extends BaseFilter {
    protected array $regions;
    protected string $latField;
    protected string $lngField;

    public function __construct(array $regions, string $latField = 'latitude', string $lngField = 'longitude') {
        $this->regions = $regions;
        $this->latField = $latField;
        $this->lngField = $lngField;
    }

    protected function filter(Enumerable $dataset): Enumerable {
        return $dataset->filter(function($row) {
            $lat = $row[$this->latField] ?? null;
            $lng = $row[$this->lngField] ?? null;

            if (!$lat || !$lng) return false;

            foreach ($this->regions as $region) {
                if ($this->pointInRegion($lat, $lng, $region)) {
                    return true;
                }
            }

            return false;
        });
    }

    protected function pointInRegion(float $lat, float $lng, array $region): bool {
        // Implementation of point-in-polygon algorithm
        // ...
    }
}

// Usage
$filter = new GeographicFilter([
    ['name' => 'US West Coast', 'bounds' => [...]],
    ['name' => 'EU Region', 'bounds' => [...]]
]);
```

---

### **4. Custom Loaders** 📤

**Extension Interface**: `Windsor\Phetl\Contracts\Loader` (To be created)

#### **Planned Loader Interface**
```php
interface Loader {
    public function load(Enumerable $data): mixed;
}
```

#### **Custom Loader Examples**

**Elasticsearch Loader**:
```php
use Windsor\Phetl\Loaders\BaseLoader; // To be created

class ElasticsearchLoader extends BaseLoader {
    protected string $index;
    protected array $mapping = [];

    public function __construct(string $index) {
        $this->index = $index;
    }

    public function mapping(array $mapping): static {
        $this->mapping = $mapping;
        return $this;
    }

    public function load(Enumerable $data): array {
        $client = app(ClientInterface::class);

        // Prepare bulk operations
        $params = ['body' => []];

        foreach ($data as $record) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index,
                    '_id' => $record['id'] ?? null
                ]
            ];

            $params['body'][] = $this->transformRecord($record);
        }

        // Execute bulk operation
        $response = $client->bulk($params);

        return [
            'indexed' => count($data),
            'errors' => $response['errors'] ?? false,
            'took' => $response['took'] ?? 0
        ];
    }

    protected function transformRecord(array $record): array {
        // Apply field mapping if configured
        if (empty($this->mapping)) {
            return $record;
        }

        $transformed = [];
        foreach ($this->mapping as $source => $target) {
            if (isset($record[$source])) {
                $transformed[$target] = $record[$source];
            }
        }

        return $transformed;
    }
}
```

**Slack Webhook Loader**:
```php
class SlackWebhookLoader extends BaseLoader {
    protected string $webhookUrl;
    protected string $messageTemplate;

    public function __construct(string $webhookUrl, string $messageTemplate = null) {
        $this->webhookUrl = $webhookUrl;
        $this->messageTemplate = $messageTemplate ?: 'Processed {count} records';
    }

    public function load(Enumerable $data): bool {
        $count = $data->count();
        $summary = $this->generateSummary($data);

        $message = str_replace(
            ['{count}', '{summary}'],
            [$count, $summary],
            $this->messageTemplate
        );

        $response = Http::post($this->webhookUrl, [
            'text' => $message,
            'attachments' => [
                [
                    'color' => 'good',
                    'fields' => [
                        ['title' => 'Records Processed', 'value' => $count, 'short' => true],
                        ['title' => 'Timestamp', 'value' => now()->toISOString(), 'short' => true]
                    ]
                ]
            ]
        ]);

        return $response->successful();
    }

    protected function generateSummary(Enumerable $data): string {
        // Generate summary statistics
        return "Data processing completed successfully";
    }
}
```

---

### **5. Custom Conditions & Criteria** 🎯

**Extension Point**: `Windsor\Phetl\Utils\Conditions\Builder`

#### **Custom Condition Methods**
```php
use Windsor\Phetl\Utils\Conditions\Builder;

// Extend Builder with custom methods
class ExtendedBuilder extends Builder {

    public function whereEmail(string $field, bool $valid = true): static {
        return $this->where($field, function($value) use ($valid) {
            $isValid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            return $valid ? $isValid : !$isValid;
        });
    }

    public function whereAge(string $field, int $min = null, int $max = null): static {
        if ($min !== null && $max !== null) {
            return $this->whereBetween($field, [$min, $max]);
        }

        if ($min !== null) {
            return $this->where($field, '>=', $min);
        }

        if ($max !== null) {
            return $this->where($field, '<=', $max);
        }

        return $this;
    }

    public function whereJsonContains(string $field, string $key, mixed $value): static {
        return $this->where($field, function($jsonString) use ($key, $value) {
            $data = json_decode($jsonString, true);
            return isset($data[$key]) && $data[$key] === $value;
        });
    }

    public function whereRegex(string $field, string $pattern): static {
        return $this->where($field, function($value) use ($pattern) {
            return preg_match($pattern, $value) === 1;
        });
    }

    public function whereGeoDistance(string $latField, string $lngField, float $centerLat, float $centerLng, float $maxDistance): static {
        return $this->where(function($row) use ($latField, $lngField, $centerLat, $centerLng, $maxDistance) {
            $lat = $row[$latField] ?? null;
            $lng = $row[$lngField] ?? null;

            if (!$lat || !$lng) return false;

            $distance = $this->calculateDistance($lat, $lng, $centerLat, $centerLng);
            return $distance <= $maxDistance;
        });
    }

    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        // Haversine formula implementation
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}

// Usage
$criteria = ExtendedBuilder::make()
    ->whereEmail('email', true)
    ->whereAge('age', 18, 65)
    ->whereJsonContains('metadata', 'source', 'api')
    ->whereGeoDistance('lat', 'lng', 40.7128, -74.0060, 50); // Within 50km of NYC

$filter = CriteriaFilter::make($criteria);
```

---

### **6. Lifecycle Hook Extensions** 🪝

**Extension Point**: Any class using `HasLifecycleHooks` trait

#### **Custom Hook Implementations**

**Monitoring Hooks**:
```php
class PerformanceMonitor {
    protected array $metrics = [];

    public function attachToExtractor(BaseExtractor $extractor): void {
        $extractor->beforeExtraction([$this, 'startExtraction']);
        $extractor->afterExtraction([$this, 'endExtraction']);
    }

    public function startExtraction($extractor): void {
        $this->metrics[get_class($extractor)] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }

    public function endExtraction($extractor, $data): void {
        $class = get_class($extractor);
        $metrics = &$this->metrics[$class];

        $metrics['end_time'] = microtime(true);
        $metrics['end_memory'] = memory_get_usage(true);
        $metrics['duration'] = $metrics['end_time'] - $metrics['start_time'];
        $metrics['memory_used'] = $metrics['end_memory'] - $metrics['start_memory'];
        $metrics['record_count'] = $data->count();
        $metrics['records_per_second'] = $metrics['record_count'] / $metrics['duration'];
    }

    public function getMetrics(): array {
        return $this->metrics;
    }
}
```

**Caching Hooks**:
```php
class CacheHook {
    protected string $cacheKey;
    protected int $ttl;

    public function __construct(string $cacheKey, int $ttl = 3600) {
        $this->cacheKey = $cacheKey;
        $this->ttl = $ttl;
    }

    public function beforeExtraction($extractor): void {
        $cached = Cache::get($this->cacheKey);
        if ($cached) {
            // Somehow bypass extraction and return cached data
            // This would require modification to the extraction flow
        }
    }

    public function afterExtraction($extractor, $data): void {
        Cache::put($this->cacheKey, $data->toArray(), $this->ttl);
    }
}
```

**Validation Hooks**:
```php
class ValidationHook {
    protected array $rules;

    public function __construct(array $rules) {
        $this->rules = $rules;
    }

    public function afterExtraction($extractor, $data): void {
        $sample = $data->take(10); // Validate sample

        foreach ($sample as $record) {
            $validator = Validator::make($record, $this->rules);

            if ($validator->fails()) {
                Log::warning('Data validation failed', [
                    'extractor' => get_class($extractor),
                    'errors' => $validator->errors()->toArray(),
                    'record' => $record
                ]);
            }
        }
    }
}
```

---

### **7. Configuration Extensions** ⚙️

#### **Custom Configuration Providers**

**Environment-Based Configuration**:
```php
class EnvironmentExtractorConfig {
    public static function getApiConfig(): array {
        return [
            'timeout' => env('PHETL_API_TIMEOUT', 30),
            'retries' => env('PHETL_API_RETRIES', 3),
            'rate_limit' => env('PHETL_API_RATE_LIMIT', 100),
            'default_headers' => [
                'User-Agent' => env('PHETL_USER_AGENT', 'Phetl ETL'),
                'Accept' => 'application/json'
            ]
        ];
    }

    public static function getDatabaseConfig(): array {
        return [
            'chunk_size' => env('PHETL_DB_CHUNK_SIZE', 1000),
            'connection' => env('PHETL_DB_CONNECTION', 'default'),
            'timeout' => env('PHETL_DB_TIMEOUT', 60)
        ];
    }
}
```

**Configuration-Driven Pipeline Builder**:
```php
class ConfigurablePipelineBuilder {
    public function buildFromConfig(array $config): EtlProcessBuilder {
        $pipeline = Phetl::pipeline();

        // Configure extractor
        $extractorConfig = $config['extractor'];
        $extractor = $this->createExtractor($extractorConfig);
        $pipeline->extract($extractor);

        // Configure transformers
        foreach ($config['transformers'] ?? [] as $transformerConfig) {
            $transformer = $this->createTransformer($transformerConfig);
            $pipeline->transform($transformer);
        }

        // Configure loader
        $loaderConfig = $config['loader'];
        $loader = $this->createLoader($loaderConfig);
        $pipeline->load($loader);

        return $pipeline;
    }

    protected function createExtractor(array $config): Extractor {
        return match($config['type']) {
            'api' => Extract::fromApi($config['endpoint'])->configure($config['options'] ?? []),
            'csv' => Extract::fromCsv($config['path'])->configure($config['options'] ?? []),
            'database' => Extract::fromQuery($config['query'])->configure($config['options'] ?? []),
            default => throw new InvalidArgumentException("Unknown extractor type: {$config['type']}")
        };
    }
}

// Usage with JSON/YAML config
$config = [
    'extractor' => [
        'type' => 'api',
        'endpoint' => 'https://api.example.com/users',
        'options' => [
            'headers' => ['Authorization' => 'Bearer token'],
            'data_path' => 'data.users'
        ]
    ],
    'transformers' => [
        ['type' => 'filter', 'criteria' => ['active' => true]],
        ['type' => 'convert', 'field' => 'created_at', 'to' => 'datetime'],
        ['type' => 'add_field', 'field' => 'processed_at', 'value' => 'now()']
    ],
    'loader' => [
        'type' => 'database',
        'table' => 'imported_users'
    ]
];

$pipeline = (new ConfigurablePipelineBuilder())->buildFromConfig($config);
```

---

## 🔧 **Extension Registration Patterns**

### **Service Provider Registration**

```php
class CustomPhetlExtensionsServiceProvider extends ServiceProvider {
    public function register(): void {
        // Register custom extractors
        $this->app->bind('phetl.extractors.mongo', MongoExtractor::class);
        $this->app->bind('phetl.extractors.redis', RedisExtractor::class);

        // Register custom transformers
        $this->app->bind('phetl.transformers.geocoding', GeocodingTransformer::class);
        $this->app->bind('phetl.transformers.ml_prediction', MLPredictionTransformer::class);

        // Register custom loaders
        $this->app->bind('phetl.loaders.elasticsearch', ElasticsearchLoader::class);
        $this->app->bind('phetl.loaders.slack', SlackWebhookLoader::class);
    }

    public function boot(): void {
        // Register global hooks
        $monitor = new PerformanceMonitor();

        $this->app->resolving(BaseExtractor::class, function($extractor) use ($monitor) {
            $monitor->attachToExtractor($extractor);
        });
    }
}
```

### **Package-Based Extensions**

**Creating Extension Packages**:
```
my-phetl-extensions/
├── src/
│   ├── Extractors/
│   │   ├── MongoExtractor.php
│   │   └── RedisExtractor.php
│   ├── Transformers/
│   │   └── GeocodingTransformer.php
│   ├── Loaders/
│   │   └── ElasticsearchLoader.php
│   └── PhetlExtensionsServiceProvider.php
├── tests/
├── composer.json
└── README.md
```

**Package composer.json**:
```json
{
    "name": "yourcompany/phetl-extensions",
    "description": "Custom extensions for Phetl ETL package",
    "require": {
        "windsorhomes/phetl": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "YourCompany\\PhetlExtensions\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourCompany\\PhetlExtensions\\PhetlExtensionsServiceProvider"
            ]
        }
    }
}
```

---

## 🎯 **Extension Best Practices**

### **1. Follow SOLID Principles**
- Single Responsibility: Each extension should have one clear purpose
- Open/Closed: Extend behavior without modifying core classes
- Interface Segregation: Implement only the interfaces you need
- Dependency Inversion: Depend on abstractions, not concretions

### **2. Maintain Laravel Conventions**
- Use Laravel's service container for dependency injection
- Follow Laravel naming conventions
- Integrate with Laravel's configuration system
- Use Laravel's validation and error handling patterns

### **3. Performance Considerations**
- Use LazyCollection for large datasets
- Implement streaming where appropriate
- Add performance monitoring hooks
- Consider memory usage in your extensions

### **4. Error Handling**
- Throw descriptive exceptions
- Use lifecycle hooks for error logging
- Provide fallback behavior where possible
- Include context in error messages

### **5. Testing Strategy**
- Unit test your extensions in isolation
- Integration test with core Phetl components
- Mock external dependencies
- Test error conditions and edge cases

---

## 🚀 **Future Extension Capabilities**

### **Planned Extension Points**

**1. Plugin Architecture**:
```php
// Auto-discovery of extensions
Phetl::discover()
    ->extractors()
    ->transformers()
    ->loaders()
    ->register();
```

**2. Middleware System**:
```php
// Pipeline middleware
$pipeline->middleware([
    'auth' => AuthMiddleware::class,
    'cache' => CacheMiddleware::class,
    'monitor' => MonitoringMiddleware::class
]);
```

**3. Event System Integration**:
```php
// Laravel event integration
Event::listen(ExtractionCompleted::class, function($event) {
    // Handle extraction completion
});
```

**4. Configuration-Based Extensions**:
```php
// Define extensions in config
'phetl' => [
    'extensions' => [
        'extractors' => [
            'mongo' => MongoExtractor::class,
            'redis' => RedisExtractor::class
        ],
        'transformers' => [
            'geocoding' => GeocodingTransformer::class
        ]
    ]
]
```

The extension system provides powerful customization capabilities while maintaining the simplicity and elegance that makes Phetl easy to use for common cases.

*Next: Read [Performance Considerations](07-performance-considerations.md) to understand how to optimize your extensions and the overall ETL performance.*