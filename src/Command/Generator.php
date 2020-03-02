<?php

declare(strict_types=1);

namespace loophp\PhptreeAstGenerator\Command;

use InvalidArgumentException;
use loophp\phptree\Exporter\Gv;
use loophp\phptree\Exporter\Image;
use loophp\phptree\Importer\MicrosoftTolerantPhpParser;
use loophp\phptree\Importer\NikicPhpParser;
use loophp\PhptreeAstGenerator\Exporter\FancyExporter;
use loophp\PhptreeAstGenerator\Exporter\MicrosoftFancyExporter;
use Microsoft\PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

class Generator extends Command
{
    /**
     * @return string
     */
    public function getHelp(): string
    {
        return <<<'EOF'
The <info>%command.name%</info> command generates an abstract syntax tree.

There are only 2 output formats that are supported:
* <info>dot</info> (The GraphViz format)
* <info>image</info> (any image format supported by GraphViz)

By default, the <info>dot</info> format is used. Use the <comment>-c</comment> option to change it.

The generator supports 2 parsers:
* <href=https://github.com/nikic/php-parser>nikic/php-parser</>
* <href=https://github.com/microsoft/tolerant-php-parser>microsoft/tolerant-php-parser</>

By default, the <info>nikic</info> parser is used. Use the <comment>-p</comment> option to change it.

The following command will generate a dot script, ready to be used by GraphViz:

    <info>$ php %command.full_name% /path/to/any/php/file</info>
    
Use the <comment>-c</comment> option to enable a <comment>fancy</comment> and user-friendly export,
easier to read and less verbose. 

If you want to set a <comment>destination</comment>, use the <comment>-d</comment> option.

The following command will generate a dot script, and save it in a file:

    <info>$ php %command.full_name% -d graph.dot /path/to/any/php/file</info>

You can change the <comment>type</comment> of export format by using the <comment>-t</comment> option.

The following command will generate an PNG image, and save it in a file:

    <info>$ php %command.full_name% -t image -f png -d graph.png /path/to/any/php/file</info>

Use the <comment>-f</comment> option to change the image <comment>format</comment>, default is SVG.

For more help:

    <info>$ php %command.full_name% -h</info>
EOF;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('generate-nikic')
            ->setDescription('Generate an Abstract Syntax Tree using nikic/php-parser parser.')
            ->setHelp($this->getHelp())
            ->addArgument('filepath', InputArgument::REQUIRED, 'Filepath to the PHP code.')
            ->addOption('parser', 'p', InputOption::VALUE_OPTIONAL, 'The parser (nikic, microsoft)', 'nikic')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The exporter type (dot, image)', 'dot')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'The export format (png, jpg, svg)', 'svg')
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The export destination (a filepath or inline)',
                'inline'
            )
            ->addOption('fancy', 'c', InputOption::VALUE_OPTIONAL, 'Use the fancy exporter ?', false);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filepath = $input->getArgument('filepath');

        if (false === is_string($filepath)) {
            throw new InvalidArgumentException('Filepath must be a string.');
        }

        if (false === $filepath = realpath($filepath)) {
            throw new InvalidArgumentException('Unable to access the given filepath.');
        }

        if (false === $fileContent = file_get_contents($filepath)) {
            throw new InvalidArgumentException('Unable to get the content of given filepath.');
        }

        $type = $input->getOption('type');
        $format = $input->getOption('format');

        if (false === is_string($format)) {
            $format = 'svg';
        }

        $parser = $input->getOption('parser');

        switch ($parser) {
            default:
                $importer = new NikicPhpParser();
                $data = (new ParserFactory())->create(ParserFactory::PREFER_PHP7)
                    ->parse($fileContent);
                $tree = $importer->import($data);

                break;
            case 'microsoft':
                $importer = new MicrosoftTolerantPhpParser();
                $data = (new Parser())->parseSourceFile($fileContent);
                $tree = $importer->import($data);

                break;
        }

        $exporter = new Gv();

        switch ($type) {
            case 'image':
                $exporter = (new Image())->setFormat($format);

                break;
        }

        if (false !== $input->getOption('fancy')) {
            // Todo: merge this decorator into a single class.
            if ('nikic' === $parser) {
                $exporter = new FancyExporter($exporter);
            }

            if ('microsoft' === $parser) {
                $exporter = new MicrosoftFancyExporter($exporter);
            }
        }

        $export = $exporter->export($tree);

        $destination = $input->getOption('destination');

        if (false === is_string($destination)) {
            return 1;
        }

        if ('inline' === $destination) {
            $output->writeln($export);

            return 0;
        }

        return (int) file_put_contents($destination, $export);
    }
}
