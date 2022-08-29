<?php
namespace Mezon;

use Mezon\Fs\Layer;
use Mezon\Functional\Fetcher;

class TestDataManager
{

    // TODO move to the separate package

    /**
     * List of the creators
     *
     * @var callable[]
     */
    private static $creators = [];

    /**
     * List of the destructors
     *
     * @var callable[]
     */
    private static $destructors = [];

    /**
     * Data desciptors
     *
     * @var array
     */
    private static $dataDescriptors = [];

    /**
     * Ids of the created entities
     *
     * @var array<string, array>
     */
    private static $ids = [];

    /**
     * List of set up scripts
     *
     * @var callable[]
     */
    private static $setUpScripts = [];

    /**
     * List of tear down scripts
     *
     * @var callable[]
     */
    private static $tearDownScripts = [];

    /**
     * Method registers test data creator
     *
     * @param string $type
     *            type of the creating entity
     * @param callable $testDataCreator
     *            test data creator
     */
    public static function registerTestDataCreator(string $type, callable $testDataCreator): void
    {
        static::$creators[$type] = $testDataCreator;
    }

    /**
     * Method registers test data destructor
     *
     * @param string $type
     *            type of the destructing entity
     * @param callable $testDataDestructor
     *            test data destructor
     */
    public static function registerTestDataDestructor(string $type, callable $testDataDestructor): void
    {
        static::$destructors[$type] = $testDataDestructor;
    }

    /**
     * Method registers set up scripts
     *
     * @param callable $script
     *            setup script
     */
    public static function registerSetUpScript(callable $script): void
    {
        static::$setUpScripts[] = $script;
    }

    /**
     * Method registers tear down scripts
     *
     * @param callable $script
     *            tear down script
     */
    public static function registerTearDownScript(callable $script): void
    {
        static::$tearDownScripts[] = $script;
    }

    /**
     * Method loads configs with testing data
     *
     * @param string $configPath
     *            path to the loading config
     */
    public static function loadConfig(string $configPath): void
    {
        $configContent = Layer::existingFileGetContents($configPath);

        $configJson = json_decode($configContent, false);

        if (is_array($configJson)) {
            static::$dataDescriptors = array_merge(static::$dataDescriptors, $configJson);
        } else {
            throw (new \Exception('Config ' . $configPath . ' must contain array', - 1));
        }
    }

    /**
     * Loading configs from directory
     *
     * @param string $path
     *            path to the directory with configs
     */
    public static function loadConfigs(string $path): void
    {
        $dirs = scandir($path);

        foreach ($dirs as $item) {
            if ($item !== '.' && $item !== '..') {
                static::loadConfig($path . '/' . $item);
            }
        }
    }

    /**
     * Method stores id of the created entity
     *
     * @param string $type
     *            type of the created entity
     * @param int $id
     *            id of the created entity
     */
    private static function storeId(string $type, int $id): void
    {
        if (! isset(static::$ids[$type])) {
            static::$ids[$type] = [];
        }

        static::$ids[$type][] = $id;
    }

    /**
     * Creating entity by descriptor
     *
     * @param object $descriptor
     *            entity descriptor
     * @return int id of the created entity
     */
    private static function createEntityByDescriptor(object $descriptor): int
    {
        $type = Fetcher::getField($descriptor, 'type', false);

        if ($type === null) {
            throw (new \Exception('Field "type" was not set for the creating entity', - 1));
        }

        if (! isset(static::$creators[$type])) {
            throw (new \Exception('Creator with the type "' . $type . '" was not found', - 1));
        }

        if (! isset(static::$destructors[$type])) {
            throw (new \Exception('Destructor with the type "' . $type . '" was not found', - 1));
        }

        $id = call_user_func(static::$creators[$type], $descriptor);

        static::storeId($type, $id);

        // nested records
        if (isset($descriptor->nested)) {
            foreach ($descriptor->nested as $nestedEntity) {
                static::createEntityByDescriptor($nestedEntity);
            }
        }

        return $id;
    }

    /**
     * Method creates entity
     *
     * @param string $name
     *            entity name
     *            
     * @param int $id
     *            of the created record
     */
    public static function requireEntity(string $name): int
    {
        foreach (static::$dataDescriptors as $descriptor) {
            if (Fetcher::getField($descriptor, 'name') === $name) {
                return static::createEntityByDescriptor($descriptor);
            }
        }

        throw (new \Exception('Data set with name "' . $name . '" was not found', - 1));
    }

    /**
     * Method runs global set up scripts
     */
    public static function setUpScripts(): void
    {
        foreach (static::$setUpScripts as $script) {
            call_user_func($script);
        }
    }

    /**
     * Method runs global tear down scripts
     */
    public static function tearDownScripts(): void
    {
        foreach (static::$tearDownScripts as $script) {
            call_user_func($script);
        }
    }

    /**
     * Method destroys created testing data
     */
    public static function tearDown(): void
    {
        foreach (static::$ids as $type => $typedIds) {
            if (isset(static::$destructors[$type])) {
                foreach ($typedIds as $id) {
                    call_user_func(static::$destructors[$type], $id);
                }
            } else {
                throw (new \Exception('Destructor for the type "' . $type . '" was not found', - 1));
            }
        }

        static::tearDownScripts();
    }

    /**
     * Method creates the last id of the created record
     *
     * @param string $type
     *            type of the entity
     * @return int id of the entity
     */
    public static function getLastId(string $type): int
    {
        if (! isset(static::$ids[$type])) {
            throw (new \Exception('Entities with type "' . $type . '" were not created', - 1));
        }

        return static::$ids[$type][count(static::$ids[$type]) - 1];
    }
}
