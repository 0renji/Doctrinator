<?php
namespace App\Command;

use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class Doctrinator extends Command
{
    protected static $defaultName = 'app:doctrinator';
    protected function configure(){
        $this
            ->setDescription('Creates Doctrine Instances of your Codeigniter Instances.')
            ->addOption('install', 'i',InputOption::VALUE_NONE, 'Tells the cli to install it\'s needed workspace')
            ->addOption('ignore','ig', InputOption::VALUE_REQUIRED, 'The directory path containing the ignore file.')
            ->addArgument('directory', InputArgument::OPTIONAL, 'The directory path containing codeigniter instances.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filesystem = new Filesystem();
        $ignoreInstallFilepath = sys_get_temp_dir().'/'.'ignore.yml';

        // Required File Pointers
        $logFile = null;
        $ignoreFile = null;

        if ($input->getOption('install')) {
            return $this->install($output, $filesystem, $ignoreInstallFilepath);
        }

        if ($input->getOption('ignore')) {
            if(!$filesystem->exists($input->getOption('ignore'))) {
                $this->outputError('Ignore path is incorrect, please use --install or -i or change the path to an absolute path.', $output);
                return Command::FAILURE;
            }

            $ignoreFile = fopen($input->getOption('ignore'), 'r+');
        } else {
            if(!$filesystem->exists($ignoreInstallFilepath)){
                $this->outputError('Ignore file not given, please use --install or -i.', $output);
                return Command::FAILURE;
            }

            $ignoreFile = fopen($ignoreInstallFilepath, 'r+');
        }

        if (!$ignoreFile) {
            if($input->getOption('ignore')) {
                $this->outputError('Ignore path is incorrect, please use --install or -i or cchange the path to an absolute path.', $output);
            } else {
                $this->outputError('Ignore file not given, please use --install or -i.', $output);
            }
            return Command::FAILURE;
        }

        // TODO check if ignore File empty output prompt that asks if continue despite empty

        if (!$input->getArgument('directory')) {
            $this->outputError('Directory as an argument is missing.', $output);
            return Command::FAILURE;
        }

        if ($this->crawl($input->getArgument('directory'), $output) == Command::SUCCESS) {
            // Do stuff
            return Command::SUCCESS;
        }

        // log stuff in error log
        $this->outputError('An unexpected error occured.', $output);
        return Command::FAILURE;
    }

    /**
     * crawls through the given path and reads every instance
     * @param string $path
     * @param OutputInterface $output
     */
    private function crawl(string $path, OutputInterface $output)
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, 0));
        /** @var SplFileInfo  $file */
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $output->writeln($file->getFilename());
        }
    }

    /**
     * creates the needed files to run the cli
     * @param OutputInterface $output
     * @param Filesystem $filesystem
     * @param string $filepath
     * @return int
     */
    private function install(OutputInterface $output, Filesystem $filesystem,string $filepath)
    {
        $this->outputInfo('installing...', $output);
        try {
            $filesystem->dumpFile($filepath, '# The ignore file for instances to be ignored.');
        } catch (IOExceptionInterface $exception) {
            $this->outputError('Failed creating the ignore file at '. $exception->getPath(), $output);
            return Command::FAILURE;
        }
        $this->outputInfo('done!', $output);

        return Command::SUCCESS;
    }

    private function outputError (string $message, OutputInterface $output) {
        $formatter = $this->getHelper('formatter');
        $formattedOutput = $formatter->formatSection(
                'Error',
                '<error> '.$message.'</error>',
                'error'
            );
        $output->writeln($formattedOutput);
    }
    private function outputInfo (string $message, OutputInterface $output) {
        $formatter = $this->getHelper('formatter');
        $formattedOutput = $formatter->formatSection(
            'Info',
            '<info> '.$message.'</info>',
            'info'
        );
        $output->writeln($formattedOutput);
    }
}