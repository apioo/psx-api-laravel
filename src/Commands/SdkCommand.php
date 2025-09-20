<?php
/*
 * PSX is an open source PHP framework to develop RESTful APIs.
 * For the current version and information visit <https://phpsx.org>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\ApiLaravel\Commands;

use Illuminate\Console\Command;
use PSX\Api\GeneratorFactory;
use PSX\Api\Repository\LocalRepository;
use PSX\Api\Scanner\FilterFactoryInterface;
use PSX\Api\ScannerInterface;
use PSX\Schema\Generator\Code\Chunks;
use PSX\Schema\Generator\Config;
use Symfony\Component\Console\Input\InputInterface;

/**
 * SdkCommand
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class SdkCommand extends Command
{
    protected $signature = 'generate:sdk';

    protected $description = 'Generates a client SDK';

    public function __construct(private string $projectDir, private ScannerInterface $scanner, private GeneratorFactory $factory, private FilterFactoryInterface $filterFactory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dir = $this->getOutputDir($this->input);
        $registry = $this->factory->factory();

        $type = $this->input->getArgument('type') ?? LocalRepository::CLIENT_TYPESCRIPT;;
        if (!is_string($type) || !in_array($type, $registry->getPossibleTypes())) {
            throw new \InvalidArgumentException('Provided an invalid type, possible values are: ' . implode(', ', $registry->getPossibleTypes()));
        }

        $config = $this->getConfig($this->input);
        $filterName = $this->input->getOption('filter');
        if (empty($filterName)) {
            $filterName = $this->filterFactory->getDefault();
        }

        $filter = null;
        if (!empty($filterName) && is_string($filterName)) {
            $filter = $this->filterFactory->getFilter($filterName);
            if ($filter === null) {
                throw new \RuntimeException('Provided an invalid filter name');
            }
        }

        $generator = $registry->getGenerator($type, $config);
        $extension = $registry->getFileExtension($type);

        $this->output->writeln('Generating ...');

        $content = $generator->generate($this->scanner->generate($filter));

        if ($content instanceof Chunks) {
            if ($this->input->getOption('raw')) {
                foreach ($content->getChunks() as $identifier => $code) {
                    file_put_contents($dir . '/' . $identifier, (string) $code);
                }
            } else {
                if (!empty($filterName)) {
                    $file = 'sdk-' . $type .  '-' . $filterName . '.zip';
                } else {
                    $file = 'sdk-' . $type .  '.zip';
                }

                $content->writeToZip($dir . '/' . $file);
            }
        } else {
            if (!empty($filterName)) {
                $file = 'output-' . $type . '-' . $filterName . '.' . $extension;
            } else {
                $file = 'output-' . $type . '.' . $extension;
            }

            file_put_contents($dir . '/' . $file, $content);
        }

        $this->output->writeln('Successful!');

        return 0;
    }

    private function getConfig(InputInterface $input): ?Config
    {
        $config = $input->getOption('config');
        if (!empty($config)) {
            return Config::fromQueryString($config);
        }

        $namespace = $input->getOption('namespace');
        $config = new Config();
        if (!empty($namespace)) {
            $config->put(Config::NAMESPACE, $namespace);
        }

        return $config;
    }

    private function getOutputDir(InputInterface $input): string
    {
        $outputDir = $input->getOption('output');
        if (is_dir($outputDir)) {
            return $outputDir;
        }

        if (is_dir($this->projectDir . '/' . $outputDir)) {
            return $this->projectDir . '/' . $outputDir;
        }

        throw new \RuntimeException('The folder ' . $outputDir . ' does not exist, please create it in order to generate the SDK');
    }
}
