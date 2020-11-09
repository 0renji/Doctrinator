<?php
namespace App\Command;

use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

class Doctrinator extends Command
{
    protected static $defaultName = 'app:doctrinator';
    protected function configure(){
        $this
            ->setDescription('Creates Doctrine Instances of your Codeigniter Instances.')
            ->addOption('install', 'i',InputOption::VALUE_NONE, 'Tells the cli to install it\'s needed workspace')
            ->addArgument('directory', InputArgument::OPTIONAL, 'The directory path containing codeigniter instances.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');

        if ($input->getOption('install')) {
            $this->install($output);
            return Command::SUCCESS;
        }

        // TODO check if the files needed are given -> ignore file
        if (!$input->getArgument('directory')) {
            $formattedOutput = $formatter->formatSection(
                'Error!',
                '<error> Directory as an argument is missing.</error>',
                'error'
            );

            $output->write($formattedOutput);
            return Command::FAILURE;
        }

        if ($this->crawl($input->getArgument('directory'), $output) == Command::SUCCESS) {
            // Do stuff
            return Command::SUCCESS;
        }

        // log stuff in error log
        $output->writeln('An unexpected error occured.');
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
     */
    private function install(OutputInterface $output)
    {
        $output->writeln('installing...');
        //TODO create the ignore file
    }
}