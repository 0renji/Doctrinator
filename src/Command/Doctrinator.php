<?php
namespace App\Command;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeDumper;
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
use App\Helpers\logHelper;
use App\Helpers\outputHelper;
use Symfony\Component\Yaml\Yaml;

class Doctrinator extends Command
{
    protected static $defaultName = 'app:doctrinator';
    protected $ignoreFilepath = null;
    protected $logFilepath = null;
    protected $destinationDirectory = null;

    protected $filesystem = null;


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
     * @param FormatterHelper $formatter
     * @param outputHelper $outputter
     * @return int
     */
    protected function handleArguments(InputInterface $input, OutputInterface $output, FormatterHelper $formatter, outputHelper $outputter) {
        // TODO check if destination and source are the same -> can't be! because of same file names
        // check of install is required
        // fill ignoreFile with the working ignore file pointer with readonly
        if ($input->getOption('install')) {
            return $this->install($output, $outputter, $formatter);
        }
        // check if the ignore path is given, and if the path works, then fill the FP
        if ($input->getOption('ignore')) {
            // check if the user given path works
            if(!$this->filesystem->exists($input->getOption('ignore'))) {
                $outputter->outputError('Ignore path is incorrect, please use --install or -i or change the path to an absolute path.', $output, $formatter);
                return Command::FAILURE;
            }
            // overwrite the current ignoreFilepath
            $this->ignoreFilepath = $input->getOption('ignore');
        } else {
            // check if the dev set path works
            if(!$this->filesystem->exists($this->ignoreFilepath)){
                $outputter->outputError('Ignore file not given, please use --install or -i.', $output, $formatter);
                return Command::FAILURE;
            }
        }
        if (filesize($this->ignoreFilepath) == 0) {
            $outputter->outputError('', $output, $formatter);
            return Command::FAILURE;
        }
        if (!$input->getArgument('sourceDirectory')) {
            $outputter->outputError('Source directory as an argument is missing.', $output, $formatter);
            return Command::FAILURE;
        }
        if (!$input->getArgument('destinationDirectory')) {
            $outputter->outputError('Destination directory as an argument is missing.', $output, $formatter);
            return Command::FAILURE;
        }

        // need the replace because of path/sub/ can be the same as path/sub but as strings it's seen as different
        if(str_replace("/", "", $input->getArgument('sourceDirectory')) == str_replace("/", "",$input->getArgument('destinationDirectory'))) {
            $outputter->outputError('Destination directory is the same as the source directory, please choose another folder.', $output, $formatter);
            return Command::FAILURE;
        }

        $this->destinationDirectory = $input->getArgument('destinationDirectory');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** Setting up */
        $outputter = new outputHelper();
        $logger = new logHelper();
        $doctriner = new doctrineHelper();
        $this->filesystem = new Filesystem();
        $formatter = $this->getHelper('formatter');
        $this->ignoreFilepath = sys_get_temp_dir().'/'.'ignore.yaml';
        /** ---------  */

        if ($this->handleArguments($input, $output, $formatter, $outputter) === Command::FAILURE) {
            return Command::FAILURE;
        };

        return $this->crawl($input->getArgument('sourceDirectory'), $output, $outputter, $formatter, $doctriner);
    }

    /**
     * creates the needed files to run the cli
     * @param OutputInterface $output
     * @param outputHelper $outputter
     * @param FormatterHelper $formatter
     * @return int
     */
    private function install(OutputInterface $output, outputHelper $outputter, FormatterHelper $formatter)
    {
        $outputter->outputInfo('installing...', $output, $formatter);

        if ($this->filesystem->exists($this->ignoreFilepath)) {
            $outputter->outputInfo('The ignore.yaml already exists under: '. $this->ignoreFilepath, $output, $formatter);
            return Command::SUCCESS;
        }
        try {
            $this->filesystem->dumpFile($this->ignoreFilepath, '# The ignore file for instances to be ignored.');
        } catch (IOExceptionInterface $exception) {
            $outputter->outputError('Failed creating the ignore file at '. $exception->getPath(), $output, $formatter);
            return Command::FAILURE;
        }
        $outputter->outputInfo('Done! You can find your ignore.yaml under: '. $this->ignoreFilepath, $output, $formatter);
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
    private function crawl(string $path, OutputInterface $output, outputHelper $outputter, FormatterHelper $formatter, doctrineHelper $doctrineHelper)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, 0));
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
                $outputter->outputError($e->getMessage() . ' for ' . $file->getFilename(), $output, $formatter);
                continue;
            }

            /**   Filter the ast with the nodeFinder */
            // all classes that extend Insitu_Instance
            $extendingClasses = $nodeFinder->find($ast, function (Node $node) {
                return $node instanceof Node\Stmt\Class_
                        && $node->extends !== null
                        && in_array('Insitu_Instance', $node->extends->parts);
            });
            // TODO split into insitu_instance extenders and others, try to find the files that are extended if not log!

            if (count($extendingClasses) > 1) {
                // TODO got several instances of Insitu_Instance in one file -> split into two files, log it also fill entity meta!
                $outputter->outputInfo('got several instances', $output, $formatter);
            } else if(count($extendingClasses) === 0) {
                $outputter->outputInfo('no instance', $output, $formatter);
                continue;
            }

            $entitiesMetaObject = [];

            foreach($extendingClasses as $extendingClass) {

                if (count($extendingClasses) > 1) {
                    // TODO log
                    $outputter->outputInfo('got several instances', $output, $formatter);
                }

                /** _types */
                $types = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\PropertyProperty && $node->name == '_types';
                });

                if (count($types) === 0) {
                    // TODO log if no types given
                    $outputter->outputInfo('no types', $output, $formatter);
                    continue;
                }

                /** Extracts the types keys and values into a php readable object */
                $typesObj = [];
                foreach ($types[0]->default->items as $type) {
                    $typesObj[$type->key->value] = $type->value->value;
                }

                /** entity metadata */
                $table = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\PropertyProperty && $node->name == '_table';
                });

                $entitiesMetaObject[$extendingClass->name->name] = [
                    'destinationDirectory' => $this->destinationDirectory,
                    'name' => $extendingClass->name->name,
                ];

                if (count($table) !== 0) {
                    $entitiesMetaObject[$extendingClass->name->name] = [
                        'destinationDirectory' => $this->destinationDirectory,
                        'name' => $extendingClass->name->name,
                        'table' => $table[0]->default->value
                    ];
                }

                /** functions */
                // TODO probably filter _construct out of it
                $classMethods = $nodeFinder->find($extendingClass, function (Node $node) {
                    return $node instanceof Node\Stmt\ClassMethod;
                });

                if (count($classMethods) === 0) {
                    // TODO log if no class methods given
                    $outputter->outputInfo('no class methods', $output, $formatter);
                    continue;
                }

                //TODO foreach for every entity inside the entityMetaObject
                $entityString = $doctrineHelper->createEntityFileString($entitiesMetaObject[$extendingClass->name->name], $typesObj, $classMethods);
                try {
                    $filename = $this->destinationDirectory . '/' . $extendingClass->name->name . '.php';
                    $outputter->outputInfo('Creating file at ' . $filename, $output, $formatter);
                    $this->filesystem->dumpFile($filename , $entityString);
                } catch (IOExceptionInterface $exception) {
                    $outputter->outputError('Failed creating the entity file at ' . $exception->getPath(), $output, $formatter);
                    return Command::FAILURE;
                }
            }
        }

//             $dumper = new NodeDumper();
//             echo $dumper->dump($ast) . "\n";

//            echo $prettyPrinter->prettyPrint($types);
//            $output->writeln('');
//            echo $prettyPrinter->prettyPrint($classMethods);

            // TODO if function is called but the name is not inside the instance log!
            // TODO delete break
        return Command::SUCCESS;
    }
}