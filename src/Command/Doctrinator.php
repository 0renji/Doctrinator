<?php
namespace App\Command;

use PhpParser\Node;
use PhpParser\PrettyPrinter;
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

class Doctrinator extends Command
{
    protected static $defaultName = 'app:doctrinator';
    protected function configure(){
        $this
            ->setDescription('Creates Doctrine Instances of your Codeigniter Instances.')
            ->addOption('install', 'i',InputOption::VALUE_NONE, 'Tells the cli to install it\'s needed workspace')
            ->addOption('ignore','ig', InputOption::VALUE_REQUIRED, 'The directory path containing the ignore file.')
            ->addArgument('sourceDirectory', InputArgument::OPTIONAL, 'The directory path containing codeigniter instances.')
            ->addArgument('destinationDirectory', InputArgument::OPTIONAL, 'The directory path that will contain doctrine entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputter = new outputHelper();
        $logger = new logHelper();
        $doctriner = new doctrineHelper();
        $formatter = $this->getHelper('formatter');

        $filesystem = new Filesystem();
        $ignoreInstallFilepath = sys_get_temp_dir().'/'.'ignore.yaml';

        // Required File Pointers
        $logFile = null;
        $ignoreFile = null;

        // check of install is required
        // fill ignoreFile with the working ignore file pointer with readonly
        if ($input->getOption('install')) {
            return $this->install($output, $outputter, $filesystem, $ignoreInstallFilepath, $formatter);
        }

        // check if a working ignore file is given
        if ($input->getOption('ignore')) {
            if(!$filesystem->exists($input->getOption('ignore'))) {
                $outputter->outputError('Ignore path is incorrect, please use --install or -i or change the path to an absolute path.', $output, $formatter);
                return Command::FAILURE;
            }

            $ignoreFile = fopen($input->getOption('ignore'), 'r');
        } else {
            if(!$filesystem->exists($ignoreInstallFilepath)){
                $outputter->outputError('Ignore file not given, please use --install or -i.', $output, $formatter);
                return Command::FAILURE;
            }

            $ignoreFile = fopen($ignoreInstallFilepath, 'r');
        }

        // if not tell user to install or create a working ignore file
        if (!$ignoreFile) {
            if($input->getOption('ignore')) {
                $outputter->outputError('Ignore path is incorrect, please use --install or -i or cchange the path to an absolute path.', $output, $formatter);
            } else {
                $outputter->outputError('Ignore file not given, please use --install or -i.', $output, $formatter);
            }
            return Command::FAILURE;
        }

        // TODO check if ignore File empty output prompt that asks if continue despite empty

        if (!$input->getArgument('sourceDirectory')) {
            $outputter->outputError('Source directory as an argument is missing.', $output, $formatter);
            return Command::FAILURE;
        }

        if (!$input->getArgument('destinationDirectory')) {
            $outputter->outputError('Destination directory as an argument is missing.', $output, $formatter);
            return Command::FAILURE;
        }

        if ($this->crawl($input->getArgument('sourceDirectory'), $output, $outputter, $formatter) == Command::SUCCESS) {
            // Do stuff
            return Command::SUCCESS;
        }

        // log stuff in error log
        $outputter->outputError('An unexpected error occured.', $output, $formatter);
        return Command::FAILURE;
    }


    /**
     * creates the needed files to run the cli
     * @param OutputInterface $output
     * @param outputHelper $outputter
     * @param Filesystem $filesystem
     * @param string $filepath
     * @param FormatterHelper $formatter
     * @return int
     */
    private function install(OutputInterface $output, outputHelper $outputter, Filesystem $filesystem, string $filepath, FormatterHelper $formatter)
    {
        $outputter->outputInfo('installing...', $output, $formatter);

        // TODO add prompt that tells you that one already exists at /tmp/ignore.yaml
        // TODO put Insitu_Instance and Collection near the files
        try {
            $filesystem->dumpFile($filepath, '# The ignore file for instances to be ignored.');
        } catch (IOExceptionInterface $exception) {
            $outputter->outputError('Failed creating the ignore file at '. $exception->getPath(), $output, $formatter);
            return Command::FAILURE;
        }
        $outputter->outputInfo('Done! You can find your ignore.yaml under: '. $filepath, $output, $formatter);

        return Command::SUCCESS;
    }

    /**
     * crawls through the given path and reads every instance
     * @param string $path
     * @param OutputInterface $output
     * @param outputHelper $outputter
     * @param FormatterHelper $formatter
     */
    private function crawl(string $path, OutputInterface $output, outputHelper $outputter, FormatterHelper $formatter)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, 0));

        // $traverser = new NodeTraverser();

        // is for traversing the AST, TypesConverter looks at the Property
        // $traverser->addVisitor(new TypesConverter());

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $nodeFinder = new NodeFinder();
        $prettyPrinter = new PrettyPrinter\Standard;

        /** @var SplFileInfo  $file */
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            // if the current file is not an instance by name skip
            if ($file->getExtension() != 'php') {
                continue;
            }

            // check if the filename is a base insitu instance
            if (str_contains(strtolower($file->getFilename()), 'insitu_')) {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            try {
                $ast = $parser->parse($code);
            } catch (Error $e){
                $outputter->outputError($e->getMessage(), $output, $formatter);
                continue;
            }

            $properties = $nodeFinder->findInstanceOf($ast, Node\Stmt\Property::class);
            $propProperties = $nodeFinder->find($properties, function (Node $node) {
                return $node instanceof Node\Stmt\PropertyProperty;
            });

            $types = $nodeFinder->find($propProperties, function (Node $node) {
                return $node instanceof Node\Expr\Array_;
            });

            // TODO log if no types given

            // $dumper = new NodeDumper();
            // echo $dumper->dump($types) . "\n";

            echo $prettyPrinter->prettyPrint($types);
            break;
        }
    }
}