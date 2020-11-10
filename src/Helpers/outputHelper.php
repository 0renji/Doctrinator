<?php
namespace App\Helpers;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\OutputInterface;

class outputHelper
{
    /**
     * @param string $message
     * @param OutputInterface $output
     * @param FormatterHelper $formatter
     */
    public function outputError (string $message, OutputInterface $output, FormatterHelper $formatter) {
        $formattedOutput = $formatter->formatSection(
            'Error',
            '<error> '.$message.'</error>',
            'error'
        );
        $output->writeln($formattedOutput);
    }

    /**
     * @param string $message
     * @param OutputInterface $output
     * @param FormatterHelper $formatter
     */
    public function outputInfo (string $message, OutputInterface $output, FormatterHelper $formatter) {
        $formattedOutput = $formatter->formatSection(
            'Info',
            '<info> '.$message.'</info>',
            'info'
        );
        $output->writeln($formattedOutput);
    }
}
