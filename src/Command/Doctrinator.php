<?php
namespace App\Command;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
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
    private $ignoreFilepath;
    private $logger;
    private $formatter;
    private $outputHelper;
    private $doctrineHelper;
    private $sourceDirectory;
    private $destinationDirectory;
    private $filesystem;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->outputHelper = new outputHelper();
        $this->doctrineHelper = new doctrineHelper();
        $this->filesystem = new Filesystem();
        $this->ignoreFilepath = sys_get_temp_dir().'/'.'ignore.yaml';

        parent::__construct();
    }

    protected function configure(){
        $this
            ->setDescription('Creates Doctrine Instances of your Codeigniter Instances.')
            ->addOption('install', 'i',InputOption::VALUE_NONE, 'Tells the cli to install it\'s needed workspace')
            ->addOption('ignore','ig', InputOption::VALUE_REQUIRED, 'The directory path containing the ignore file.')
            ->addArgument('sourceDirectory', InputArgument::OPTIONAL, 'The directory path containing codeigniter instances.')
            ->addArgument('destinationDirectory', InputArgument::OPTIONAL, 'The directory path that will contain doctrine entities.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function handleArguments(InputInterface $input, OutputInterface $output) {
        // check of install is required
        // fill ignoreFile with the working ignore file pointer with readonly
        if ($input->getOption('install')) {
            return $this->install($output);
        }
        // check if the ignore path is given, and if the path works, then fill the FP
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
                $this->outputHelper->outputError('Ignore file not given, please use --install or -i.', $output, $this->formatter);
                return Command::FAILURE;
            }
        }
        if (filesize($this->ignoreFilepath) == 0) {
            $this->outputHelper->outputError('', $output, $this->formatter);
            return Command::FAILURE;
        }
        if (!$input->getArgument('sourceDirectory')) {
            $this->outputHelper->outputError('Source directory as an argument is missing.', $output, $this->formatter);
            return Command::FAILURE;
        }
        if (!$input->getArgument('destinationDirectory')) {
            $this->outputHelper->outputError('Destination directory as an argument is missing.', $output, $this->formatter);
            return Command::FAILURE;
        }

        // need the replace because of path/sub/ can be the same as path/sub but as strings it's seen as different
        if(str_replace("/", "", $input->getArgument('sourceDirectory')) == str_replace("/", "",$input->getArgument('destinationDirectory'))) {
            $this->outputHelper->outputError('Destination directory is the same as the source directory, please choose another folder.', $output, $this->formatter);
            return Command::FAILURE;
        }

        $this->destinationDirectory = $input->getArgument('destinationDirectory');
        $this->sourceDirectory = $input->getArgument('sourceDirectory');
        return Command::SUCCESS;
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

        if ($this->handleArguments($input, $output) === Command::FAILURE) {
            return Command::FAILURE;
        };

        if ($this->crawl($output) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->outputHelper->outputInfo('Finished! A log file with TODOs and info\'s can be found under -> var/log/dev.log or var/log/prod.log.' , $output, $this->formatter);
        return Command::SUCCESS;
    }

    /**
     * creates the needed files to run the cli
     * @param OutputInterface $output
     * @return int
     */
    private function install(OutputInterface $output)
    {
        $this->outputHelper->outputInfo('installing...', $output, $this->formatter);

        if ($this->filesystem->exists($this->ignoreFilepath)) {
            $this->outputHelper->outputInfo('The ignore.yaml already exists under: '. $this->ignoreFilepath, $output, $this->formatter);
            return Command::SUCCESS;
        }
        try {
            $this->filesystem->dumpFile($this->ignoreFilepath, '# The ignore file for instances to be ignored.');
        } catch (IOExceptionInterface $exception) {
            $this->outputHelper->outputError('Failed creating the ignore file at '. $exception->getPath(), $output, $this->formatter);
            return Command::FAILURE;
        }
        $this->outputHelper->outputInfo('Done! You can find your ignore.yaml under: '. $this->ignoreFilepath, $output, $this->formatter);
        return Command::SUCCESS;
    }

    /**
     * crawls through the given path and reads every instance
     * @param string $path
     * @param OutputInterface $output
     * @param outputHelper $outputter
     * @param FormatterHelper $formatter
     * @param doctrineHelper $doctrineHelper
     * @return int
     */
    private function crawl(OutputInterface $output)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourceDirectory, 0));
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder();

        // parse ignore.yaml
        $ignoreYaml = Yaml::parse(file_get_contents($this->ignoreFilepath));
        // $output->writeln(Yaml::dump($ignoreYaml['Instances'][0]));
        $filesToIgnore = $ignoreYaml['Instances'];

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
            if (str_contains(strtolower($file->getFilename()), 'insitu_')
                || $filesToIgnore && in_array($file->getFilename(), $filesToIgnore)) {
                continue;
            }
            /** ------------ */

            $code = file_get_contents($file->getPathname());

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
                        && $node->extends !== null;
            });

            if(count($extendingClasses) === 0) {
                $this->outputHelper->outputInfo('no instance at ' .$file->getFilename(), $output, $this->formatter);
                continue;
            }

            $entitiesMetaObject = [];

            foreach($extendingClasses as $extendingClass) {
                if (count($extendingClasses) > 1) {
                    $this->logger->info(' Detected several classes inside of one file, the program will create one file for each class inside ' . $file->getFilename());
                }

                /** LOGGING handles the classes that are extended */
                foreach ($extendingClass->extends->parts as $part) {
                    if (strpos($part, 'Collection')) {
                        $this->logger->info('The ' . $extendingClass->name->name . ' extends the Collection ' . $part . ' inside of ' . $file->getFilename()
                            . 'Collections are currently not supported...'
                            . '// TODO establish a relation to the corresponding Instance by hand.');
                    } // if it's not an Insitu_Instance search for the instance name inside the filenames, if it's not in there log it
                    else if (!strpos($part, 'Insitu_Instance') && strpos($part, 'Instance')) {
                        $filesInDir = scandir($this->sourceDirectory);
                        if (in_array($part . '.php', $filesInDir)) {
                            $this->logger->info('The ' . $extendingClass->name->name . ' extends the Instance ' . $part . ' inside of ' . $file->getFilename()
                                . ' // TODO This instance seems to be inside the sourceDirectory and will be created but the relation needs to be established by hand.');
                        } else {
                            $this->logger->info('The ' . $extendingClass->name->name . ' extends the ' . $part . ' inside of ' . $file->getFilename() . ' which is not inside the sourceDirectory'
                                . ' // TODO Either create the missing entity by hand or restart doctrinator with the sourceDirectory containing the missing instance and the extending Instance ' . $extendingClass->name->name . '.');
                        }
                    }
                }

                if(strpos($extendingClass->name->name, 'Collection')) {
                    $this->logger->info('The ' . $extendingClass->name->name . ' is a Collection' . $part . ' inside of ' . $file->getFilename()
                        . 'Collections are currently not supported...'
                        . '// TODO create the collection by hand.');
                    continue;
                }

                /** _types */
                $types = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\PropertyProperty && $node->name == '_types';
                });

                $typesObj = [];
                if (count($types) === 0) {
                    $this->logger->info('Following Instance found without _types: ' . $extendingClass->name->name . ' inside of ' . $file->getFilename() . '.'
                    . ' // TODO An entity will still be created, attributes / fields need to be created by hand.');
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

                if (count($table) !== 0) {
                    $entitiesMetaObject[$extendingClass->name->name]['table'] = $table[0]->default->value;
                }

                /** class functions */
                // TODO probably filter _construct out of it
                $classMethods = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\ClassMethod;
                });

                if (count($classMethods) === 0) {
                    $this->logger->info('The class ' . $extendingClass->name->name . ' has no class methods.'
                        .' // TODO Please check the original for missing functionalities');
                }

                $entityString = $this->doctrineHelper->createEntityFileString($entitiesMetaObject[$extendingClass->name->name], $typesObj, $classMethods);
                try {
                    $filename = $this->destinationDirectory . '/' . $extendingClass->name->name . '.php';
                    $this->outputHelper->outputInfo('Creating file at ' . $filename, $output, $this->formatter);
                    $this->filesystem->dumpFile($filename , $entityString);
                } catch (IOExceptionInterface $exception) {
                    $this->outputHelper->outputError('Failed creating the entity file at ' . $exception->getPath(), $output, $this->formatter);
                    return Command::FAILURE;
                }
            }
        }
        return Command::SUCCESS;
    }
}