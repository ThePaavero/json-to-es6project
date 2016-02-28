<?php

require 'lib/CodeGenerator.php';

$templateName = isset($argv[1]) && ! empty($argv[1]) ? $argv[1] : '';
! empty($templateName) or die('Need template as first and only argument!' . PHP_EOL);

$generator = new CodeGenerator($argv[1]);

if ( ! $generator->templateJsonFileExists())
{
    die('Template "' . $templateName . '" does not exist!' . PHP_EOL);
}

$generator->run();

$separator = '-------------------' . PHP_EOL;

echo $separator . 'Done!' . PHP_EOL . $separator;
echo $separator . 'Next, copy your project to your development directory. Then run "npm install" and "jspm install".' . PHP_EOL . $separator;
