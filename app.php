#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Kostajh\TodoistStandup\Command\GenerateStandup;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$application = new Application();

$application->add(new GenerateStandup());
$application->run();
