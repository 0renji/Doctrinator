<?php
namespace App\Command;

use Psr\Log\LoggerInterface;
use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;
use App\Helpers\doctrineHelper;
use App\Helpers\outputHelper;
use Symfony\Component\Yaml\Yaml;

class Doctrinator extends Command
{
    protected static $defaultName = 'app:doctrinator';
    // Needed files
    private $ignoreFilepath;
    private $doctrineTypesMapperFilepath;
    // Logger Files
    private $devLogPath ='var/log/dev.log';
    private $prodLogPath ='var/log/prod.log';
    // Helper
    private $logger;
    private $formatter;
    private $questioner;
    private $filesystem;
    // Self-Made Helper
    private $outputHelper;
    private $doctrineHelper;
    // Directories
    private $sourceDirectory;
    private $destinationDirectory;


    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->outputHelper = new outputHelper();
        $this->doctrineHelper = new doctrineHelper();
        $this->filesystem = new Filesystem();
        $this->ignoreFilepath = sys_get_temp_dir().'/'.'ignore.yaml';
        $this->doctrineTypesMapperFilepath = sys_get_temp_dir().'/'.'doctrineTypesMapper.yaml';
        parent::__construct();
    }

    protected function configure(){
        $this
            ->setDescription('Creates Doctrine Instances of your Codeigniter Instances.')
            ->addOption('install', 'i',InputOption::VALUE_NONE, 'Tells the cli to install it\'s needed workspace.')
            ->addOption('ignore','x', InputOption::VALUE_REQUIRED, 'The directory path containing the ignore file.')
            ->addOption('types', 't', InputOption::VALUE_REQUIRED, 'The directory path containing the doctrineTypesMapper file.')
            ->addOption('clearlog', 'c', InputOption::VALUE_NONE, 'Tells the cli to clear the log before filling it.')
            ->addArgument('sourceDirectory', InputArgument::OPTIONAL, 'The directory path containing codeigniter instances.')
            ->addArgument('destinationDirectory', InputArgument::OPTIONAL, 'The directory path that will contain doctrine entities.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // needs to be initialized here and not in construct, it relies on the parents construct
        $this->formatter = $this->getHelper('formatter');
        $this->questioner = $this->getHelper('question');

        // check of install is required
        if ($input->getOption('install')) {
            return $this->install($output);
        }

        if ($this->handleArguments($input, $output) === Command::FAILURE) {
            return Command::FAILURE;
        };

        if ($this->crawl($output) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->outputHelper->outputInfo(
            'Finished! A log file with TODOs and info\'s can be found under -> '
            . $this->devLogPath . ' or ' . $this->prodLogPath . '.' , $output, $this->formatter);
        return Command::SUCCESS;
    }

    /**
     * crawls through the given path and reads every instance
     * @param OutputInterface $output
     * @return int
     */
    private function crawl(OutputInterface $output)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourceDirectory, 0));
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder();

        /** PARSE ignore.yaml */

        $ignoreYaml = Yaml::parse(file_get_contents($this->ignoreFilepath));

        $filesToIgnore = [];
        $instancesToIgnore = [];

        if (isset($ignoreYaml['Files'])) {
            $filesToIgnore = $ignoreYaml['Files'];
        }
        if (isset($ignoreYaml['Instances'])) {
            $instancesToIgnore = $ignoreYaml['Instances'];
        }

        $generalLogs = [];
        $collectionLogs = [];
        $instanceLogs = [];

        /** @var SplFileInfo  $file */
        foreach ($rii as $file) {
            /** FILE CHECKS */
            if ($file->isDir()) {
                continue;
            }
            // if the current file is not php skip
            if ($file->getExtension() != 'php') {
                continue;
            }
            // check if the filename is a base insitu instance skip
            if (count($filesToIgnore) > 0) {
                if (str_contains(strtolower($file->getFilename()), 'insitu_')
                    || $filesToIgnore && in_array($file->getFilename(), $filesToIgnore)) {
                    continue;
                }
            }
            /** ------------ */

            $code = file_get_contents($file->getPathname());

            /** PARSE the code into AST */
            try {
                $ast = $parser->parse($code);
            } catch (Error $e){
                $this->outputHelper->outputError($e->getMessage() . ' for ' . $file->getFilename(), $output, $this->formatter);
                continue;
            }

            /**   Filter the ast with the nodeFinder */
            // all classes that extend Insitu_Instance
            $extendingClasses = $nodeFinder->find($ast, function (Node $node) {
                return $node instanceof Node\Stmt\Class_
                    && $node->extends !== null
                    && !str_contains($node->extends, 'Exception')
                    && !str_contains($node->extends, 'Model');
            });

            if(count($extendingClasses) === 0) {
                $this->outputHelper->outputInfo('no instance at ' .$file->getFilename(), $output, $this->formatter);
                continue;
            }

            $entitiesMetaObject = [];

            foreach($extendingClasses as $extendingClass) {
                if (count($extendingClasses) > 1) {
                    $generalLogs[$file->getFilename()]['messages'][] = 'Detected several classes inside of one file, the program will create one file for each class inside ' . $file->getFilename();
                }

                /** LOGGING AND SKIPPING handles the classes that are extended */
                // looks at the class behind the "extends" expression -> $part
                foreach ($extendingClass->extends->parts as $part) {
                    if(strpos($part, 'Model')) {
                        continue 2;
                    }
                    $logMessage = '';
                    $todo = '';

                    // if class extends a Collection
                    if (strpos($part, 'Collection')) {
                        continue 2;
                    } // if it's not an Insitu_Instance search for the instance name inside the filenames, if it's not in there log it
                    else if (!strpos($part, 'Insitu_Instance') && strpos($part, 'Instance')) {
                        $filesInDir = scandir($this->sourceDirectory);
                        if (in_array($part . '.php', $filesInDir)) {
                            $logMessage = 'The ' . $extendingClass->name->name . ' extends the Instance ' . $part . ' inside of ' . $file->getFilename();
                            $todo = '// TODO This instance seems to be inside the sourceDirectory and will be created but the relation needs to be established by hand.';
                        }
                    }
                    else {
                        $logMessage = 'The ' . $extendingClass->name->name . ' extends the ' . $part . ' inside of ' . $file->getFilename() . ' which is not inside the sourceDirectory';
                        $todo = ' // TODO Either create the missing entity by hand or restart doctrinator with the sourceDirectory containing the missing instance and the extending Instance ' . $extendingClass->name->name . '.';
                    }

                    // check if collection or instance
                    if ($logMessage !== '') {
                        if (strpos($part, 'Collection')) {
                            $collectionLogs[$extendingClass->name->name]['messages'][] = $logMessage;
                            $collectionLogs[$extendingClass->name->name]['todos'][] = $todo;
                        } else {
                            $instanceLogs[$extendingClass->name->name]['messages'][] = $logMessage;
                            $instanceLogs[$extendingClass->name->name]['todos'][] = $todo;
                        }
                    }
                }

                /** SKIP IF TRUE */
                if(strpos($extendingClass->name->name, 'Collection')
                    ||strpos($extendingClass->name->name, 'Model')
                    ||strpos($extendingClass->name->name, 'Exception')
                    ||in_array($extendingClass->name->name, $instancesToIgnore)) {
                    continue;
                }

                /** _types */
                $types = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\PropertyProperty && $node->name == '_types';
                });

                $typesObj = [];
                if (count($types) === 0) {
                    $instanceLogs[$extendingClass->name->name]['messages'][] =
                        'Following Instance found without _types: ' . $extendingClass->name->name . ' inside of ' . $file->getFilename() . '.';
                    $instanceLogs[$extendingClass->name->name]['todos'][] =
                        '// TODO An entity will still be created, attributes / fields need to be created by hand.';
                } else {
                    /** Extracts the types keys and values into a php readable object */
                    foreach ($types[0]->default->items as $type) {
                        $typesObj[$type->key->value] = $type->value->value;
                    }
                }

                /** entity metadata */
                $table = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\PropertyProperty && $node->name == '_table';
                });

                $entitiesMetaObject[$extendingClass->name->name] = [
                    'destinationDirectory' => $this->destinationDirectory,
                    'name' => $extendingClass->name->name,
                    'table' => null
                ];

                if (count($table) !== 0 && $table[0]->default && isset($table[0]->default->value)) {
                    $entitiesMetaObject[$extendingClass->name->name]['table'] = $table[0]->default->value;
                }

                /** class functions */
                $classMethods = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\ClassMethod;
                });

                if (count($classMethods) === 0) {
                    $instanceLogs[$extendingClass->name->name]['messages'][] =
                        'The class ' . $extendingClass->name->name . ' has no class methods.';
                    $instanceLogs[$extendingClass->name->name]['todos'][] =
                        '// TODO Please check the original for missing functionalities';
                }

                /** Logging */
                // filter could happen above but I want to log if there is a construct inside
                // deleting the function so it doesn't interfere when validating doctrine entities
                foreach($classMethods as $method) {
                    if ($method->name->name === '__construct') {
                        $instanceLogs[$extendingClass->name->name]['messages'][] =
                            'Constructor function found inside of ' . $extendingClass->name->name .' will be removed for doctrine validation reasons.';

                       // $node->stmts = [];
                    }
                }

                // filtering contruct out of the methods
                $classMethods = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\ClassMethod && $node->name->name !== '__construct';
                });

                $entityObject = $this->doctrineHelper->createEntityFileString($entitiesMetaObject[$extendingClass->name->name], $typesObj, $classMethods, $this->doctrineTypesMapperFilepath);

                $entityString = $entityObject['entityString'];

                // add log messages from templating
                if ($entityObject['logMessages'] && count($entityObject['logMessages']) > 0) {
                    foreach ($entityObject['logMessages'] as $logMessage) {
                        $instanceLogs[$extendingClass->name->name]['messages'][] = $logMessage;
                    }
                }

                try {
                    $filename = $this->destinationDirectory . '/' . $extendingClass->name->name . '.php';
                    $this->outputHelper->outputInfo('Creating file at ' . $filename, $output, $this->formatter);
                    $this->filesystem->dumpFile($filename , $entityString);
                } catch (IOExceptionInterface $exception) {
                    $this->outputHelper->outputError('Failed creating the entity file at ' . $exception->getPath(), $output, $this->formatter);
                    return Command::FAILURE;
                }
            }

            if(isset($generalLogs[$file->getFilename()])) {
                $this->logAllForFile($file->getFilename(), $generalLogs[$file->getFilename()], $collectionLogs, $instanceLogs);
            }

            // reset
            $collectionLogs = [];
            $instanceLogs = [];
        }
        return Command::SUCCESS;
    }

    /**
     * creates the needed files to run the cli
     * @param OutputInterface $output
     * @return int
     */
    private function install(OutputInterface $output)
    {
        $this->outputHelper->outputInfo('Installing...', $output, $this->formatter);

        $this->createIgnoreFile($output);
        $this->createDoctrineTypesMapperFile($output);

        $this->outputHelper->outputInfo('Done! You can find the installed files under: '. $this->ignoreFilepath . ' ' . $this->doctrineTypesMapperFilepath, $output, $this->formatter);
        return Command::SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function handleArguments(InputInterface $input, OutputInterface $output) {
        /** LOGGER OPTIONS */
        if ($input->getOption('clearlog')) {
            // check if log exists
            if ($this->filesystem->exists($this->devLogPath)) {
                $this->outputHelper->outputInfo('Clearing your development log at ' . $this->devLogPath . '.', $output, $this->formatter);
                file_put_contents($this->devLogPath, '');
            }

            if($this->filesystem->exists($this->prodLogPath)) {
                $this->outputHelper->outputInfo('Clearing your production log at ' . $this->prodLogPath . '.', $output, $this->formatter);
                file_put_contents($this->prodLogPath, '');
            }
        }

        /** FILEPATH OPTIONS */
        // check if the ignore path is given, and if the path works, if not fill with default
        if ($input->getOption('ignore')) {
            // check if the user given path works
            if(!$this->filesystem->exists($input->getOption('ignore'))) {
                $this->outputHelper->outputError('Ignore path is incorrect, please use --install or -i or change the path to an absolute path.', $output, $this->formatter);
                return Command::FAILURE;
            }
            // overwrite the current ignoreFilepath
            $this->ignoreFilepath = $input->getOption('ignore');
        } else {
            // check if the dev set path works
            if(!$this->filesystem->exists($this->ignoreFilepath)){
                $this->outputHelper->outputError('Default file path for the ignore.yaml not working, please use --install or -i or manually create the file and link it with --types=path/to/file.yaml in your call.', $output, $this->formatter);
                return Command::FAILURE;
            }
        }

        if (filesize($this->ignoreFilepath) == 0) {
            $this->outputHelper->outputInfo('Your ignore file is empty.', $output, $this->formatter);
            $question = new ConfirmationQuestion(
                'Go on without ignoring any files in your source directory?', false);

            if ($this->questioner->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }

            return Command::FAILURE;
        }

        if ($input->getOption('types')) {
            // check if the user given path works
            if(!$this->filesystem->exists($input->getOption('types'))) {
                $this->outputHelper->outputError('Dcotrine Types Mapper path is incorrect, please use --install or -i or change the path to an absolute path.', $output, $this->formatter);
                return Command::FAILURE;
            }
            // overwrite the current path
            $this->doctrineTypesMapperFilepath = $input->getOption('types');
        } else {
            // check if the dev set path works
            if(!$this->filesystem->exists($this->doctrineTypesMapperFilepath)){
                $this->outputHelper->outputError('Default file path for the doctrineTypesMapper.yaml not working, please use --install or -i or manually create the file and link it with --types=path/to/file.yaml in your call.', $output, $this->formatter);
                return Command::FAILURE;
            }
        }

        if (filesize($this->doctrineTypesMapperFilepath) == 0) {
            $this->outputHelper->outputError('Your doctrineTypesMapper file is empty, please define type mappings, see the installed doctrineTypesMapper.yaml comments for instructions after installing.', $output, $this->formatter);
            $question = new ConfirmationQuestion('Go on without having types mapped?',false);

            if (!$this->questioner->ask($input, $output, $question)) {
                return Command::FAILURE;
            }
        }

        /** DIRECTORY ARGUMENTS */
        if (!$input->getArgument('sourceDirectory')) {
            $this->outputHelper->outputError('Source directory as an argument is missing.', $output, $this->formatter);
            return Command::FAILURE;
        }
        if (!$input->getArgument('destinationDirectory')) {
            $this->outputHelper->outputError('Destination directory as an argument is missing.', $output, $this->formatter);
            return Command::FAILURE;
        }

        // needs the str_replace because of path/sub/ can be the same as path/sub for an OS but as strings they're different
        if(str_replace("/", "", $input->getArgument('sourceDirectory')) == str_replace("/", "",$input->getArgument('destinationDirectory'))) {
            $this->outputHelper->outputError('Destination directory is the same as the source directory, please choose another folder.', $output, $this->formatter);
            return Command::FAILURE;
        }

        $this->destinationDirectory = $input->getArgument('destinationDirectory');
        $this->sourceDirectory = $input->getArgument('sourceDirectory');
        return Command::SUCCESS;
    }

    /**
     * @param OutputInterface $output
     * @return int
     */
    private function createIgnoreFile(OutputInterface $output) {
        if ($this->filesystem->exists($this->ignoreFilepath)) {
            $this->outputHelper->outputInfo('The ignore.yaml already exists under: '. $this->ignoreFilepath, $output, $this->formatter);
            return Command::SUCCESS;
        }

        $this->outputHelper->outputInfo('creating a ignore.yaml at ' . $this->ignoreFilepath, $output, $this->formatter);

        try {
            $this->filesystem->dumpFile($this->ignoreFilepath,
                '# The ignore file for instances to be ignored.' . "\n"
                . '# Example entry for an instance would be:' . "\n"
                . '# Instances:' . "\n"
                . '#' . "\t" . '- "Example_Instance.php"' . "\n");
        } catch (IOExceptionInterface $exception) {
            $this->outputHelper->outputError('Failed creating the ignore file at '. $exception->getPath(), $output, $this->formatter);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param OutputInterface $output
     * @return int
     */
    private function createDoctrineTypesMapperFile (OutputInterface $output) {

        if ($this->filesystem->exists($this->doctrineTypesMapperFilepath)){
            $this->outputHelper->outputInfo('The doctrineTypesMapper.yaml already exists under: '. $this->doctrineTypesMapperFilepath, $output, $this->formatter);
            return Command::SUCCESS;
        }

        $this->outputHelper->outputInfo('creating a doctrineTypesMapper.yaml at ' . $this->doctrineTypesMapperFilepath, $output, $this->formatter);

        try {
            $this->filesystem->appendToFile($this->doctrineTypesMapperFilepath,
                '# The doctrine types mapper file for the creation of your entities.' . "\n"
                . '# The structure should be like this:');
            $array = [
                'All' => [
                    'id' => [
                        'type' => 'integer',
                        'generator' => [
                            'strategy' => 'AUTO'
                        ]
                    ],
                    'id_or_dbdefault' => [
                        'type' => 'integer',
                        'generator' => [
                            'strategy' => 'AUTO'
                        ]
                    ],
                    'spacetime' => [
                        'type' => 'datetime'
                    ],
                    'spacetime_or_dbdefault' => [
                        'type' => 'datetime'
                    ],
                    'bool' => [
                        'type' => 'boolean'
                    ],
                    'bool_or_null' => [
                        'type' => 'boolean'
                    ],
                ],
                'Exceptions' => [],
            ];

            $yaml = Yaml::dump($array, 5);

            $this->filesystem->appendToFile($this->doctrineTypesMapperFilepath,  "\n\n". $yaml);

            $this->filesystem->appendToFile($this->doctrineTypesMapperFilepath,
                "\n" .'# Example for Exception entities:' . "\n" .
                '# EntityName => [ ' . "\n" .
                '#' . "\t" . '\'type\' => \'entity\',' . "\n" .
                '#' . "\t" . ' \'id\' => [],' . "\n" .
                '#' . "\t" . ' \'fields\' => [],' . "\n".
                '# ]' . "\n");

        } catch (IOExceptionInterface $exception) {
            $this->outputHelper->outputError('Failed creating the doctrineTypes file at '. $exception->getPath(), $output, $this->formatter);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $filename
     * @param array $generalLogs
     * @param array $collectionLogs
     * @param array $instanceLogs
     */
    private function logAllForFile(string $filename, array $generalLogs, array $collectionLogs, array $instanceLogs) {
        $this->logger->info('---  GENERAL LOGS FOR ' . $filename . ' ---');
        $this->logMessages(array_unique($generalLogs['messages']));
        $this->logThing($instanceLogs, 'INSTANCE');
        $this->logThing($collectionLogs, 'COLLECTION');
    }

    /**
     * @param array $logs
     * @param string $thingName
     */
    private function logThing(array $logs, string $thingName) {
        foreach ($logs as $log => $logArray) {
            $this->logger->info('--- '. $thingName. ' LOGS FOR ' . $log . ' ---');
            $this->logMessages(array_unique($logArray['messages']));
        }
    }

    /**
     * @param array $logMessages
     */
    private function logMessages(array $logMessages) {
        foreach ($logMessages as $log) {
            $this->logger->info($log);
        }
    }
}