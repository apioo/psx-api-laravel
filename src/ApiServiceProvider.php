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

namespace PSX\ApiLaravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use PSX\Api\ApiManager;
use PSX\Api\ApiManagerInterface;
use PSX\Api\GeneratorFactory;
use PSX\Api\Parser\Attribute\Builder;
use PSX\Api\Parser\Attribute\BuilderInterface;
use PSX\Api\Repository;
use PSX\Api\Scanner\FilterFactory;
use PSX\Api\Scanner\FilterFactoryInterface;
use PSX\Api\ScannerInterface;
use PSX\ApiLaravel\Api\Parser\LaravelAttribute;
use PSX\ApiLaravel\Api\Repository\SDKgen\Config;
use PSX\ApiLaravel\Api\Scanner\RouterScanner;
use PSX\ApiLaravel\Http\ParameterReader;
use PSX\ApiLaravel\Http\RequestReader;
use PSX\ApiLaravel\Http\ResponseBuilder;
use PSX\Data\Configuration;
use PSX\Data\Processor;
use PSX\Data\Writer;
use PSX\Schema\SchemaManager;
use PSX\Schema\SchemaManagerInterface;

/**
 * ApiServiceProvider
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class ApiServiceProvider extends IlluminateServiceProvider
{
    public function register()
    {
        $this->app->configPath(__DIR__ . '/../config/psx.php');

        $this->app->singleton(SchemaManager::class, function (Application $app) {
            return new SchemaManager($app['cache.psr6'], debug: $app->hasDebugModeEnabled());
        });

        $this->app->bind(SchemaManagerInterface::class, SchemaManager::class);

        $this->app->singleton(Processor::class, function (Application $app) {
            $config = Configuration::createDefault($app[SchemaManagerInterface::class]);

            return new Processor($config);
        });

        $this->app->singleton(Builder::class, function (Application $app) {
            return new Builder($app['cache.psr6'], debug: $app->hasDebugModeEnabled());
        });

        $this->app->bind(BuilderInterface::class, Builder::class);

        $this->app->singleton(RouterScanner::class, function (Application $app) {
            return new RouterScanner($app['router'], $app[ApiManagerInterface::class]);
        });

        $this->app->bind(ScannerInterface::class, RouterScanner::class);

        $this->app->singleton(FilterFactory::class, function () {
            return new FilterFactory();
        });

        $this->app->bind(FilterFactoryInterface::class, FilterFactory::class);

        $this->app->singleton(Repository\LocalRepository::class, function () {
            return new Repository\LocalRepository();
        });

        $this->app->singleton(Repository\SchemaRepository::class, function () {
            return new Repository\SchemaRepository();
        });

        $this->app->singleton(Repository\SDKgenRepository::class, function (Application $app) {
            return new Repository\SDKgenRepository($app[Repository\SDKgen\ConfigInterface::class]);
        });

        $this->app->tag([Repository\LocalRepository::class, Repository\SchemaRepository::class, Repository\SDKgenRepository::class], 'psx.api_repository');

        $this->app->singleton(Config::class, function (Application $app) {
            return new Config(
                $app['config']->get('psx.sdkgen_client_id'),
                $app['config']->get('psx.sdkgen_client_secret')
            );
        });

        $this->app->bind(Repository\SDKgen\ConfigInterface::class, Config::class);

        $this->app->singleton(GeneratorFactory::class, function (Application $app) {
            return new GeneratorFactory($app->tagged('psx.api_repository'));
        });

        $this->app->singleton(ApiManager::class, function (Application $app) {
            $manager = new ApiManager($app[SchemaManagerInterface::class], $app[BuilderInterface::class], $app['cache.psr6'], $app->hasDebugModeEnabled());
            $manager->register('php', new LaravelAttribute(
                $app[RouteCollectionInterface::class],
                $app[SchemaManagerInterface::class],
                $app[BuilderInterface::class]
            ));

            return $manager;
        });

        $this->app->bind(ApiManagerInterface::class, ApiManager::class);

        $this->app->singleton(RequestReader::class, function (Application $app) {
            return new RequestReader($app[Processor::class]);
        });

        $this->app->singleton(ParameterReader::class, function (Application $app) {
            return new ParameterReader();
        });

        $this->app->singleton(ResponseBuilder::class, function (Application $app) {
            return new ResponseBuilder($app[Processor::class], [
                Writer\Json::class,
                Writer\Jsonp::class,
                Writer\Jsonx::class,
            ]);
        });

        $this->app->singleton(Commands\ModelCommand::class, function (Application $app) {
            return new Commands\ModelCommand($this->app->basePath(), $app[SchemaManagerInterface::class]);
        });

        $this->app->singleton(Commands\SdkCommand::class, function (Application $app) {
            return new Commands\SdkCommand($this->app->basePath(), $app[ScannerInterface::class], $app[GeneratorFactory::class], $app[FilterFactoryInterface::class]);
        });
    }
}
