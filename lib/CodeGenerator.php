<?php

class CodeGenerator
{
    public $templateDirectory = __DIR__ . '/../templates/';
    public $tab = '    ';
    public $projectPath;
    public $projectSourcePath;
    public $projectName;
    public $classTemplate;
    public $author;
    public $classesToImportToMain = [];
    public $domain = '';

    public function __construct($templateName)
    {
        $this->templateName = $templateName;
        $this->templateFilePath = $this->templateDirectory . $this->templateName . '.json';
    }

    public function templateJsonFileExists()
    {
        return file_exists($this->templateFilePath);
    }

    public function run()
    {
        $template = file_get_contents($this->templateFilePath);
        $data = json_decode($template);

        if (json_last_error())
        {
            throw new Exception('malformed JSON data in template');
        }

        $this->createFiles($data);
    }

    public function createFiles($data)
    {
        $this->projectName = $data->projectName;
        $this->author = $data->author;
        $this->domain = $data->domain;

        $this->projectPath = __DIR__ . '/../generated/' . $data->projectName . '/';
        $this->projectSourcePath = $this->projectPath . 'src/js/';

        if (is_dir($this->projectPath))
        {
            die('Project directory already exists, aborting!' . PHP_EOL);
        }

        echo 'Creating project directory in /generated/' . $this->projectName . PHP_EOL;
        mkdir($this->projectPath);
        mkdir($this->projectPath . 'src');
        mkdir($this->projectSourcePath);

        // Copy our JSPM stuff
        $this->copyBoilerplateCode();

        $this->classTemplate = file_get_contents(__DIR__ . '/templates/class.js');

        foreach ($data->classes as $dirName => $classes)
        {
            echo 'Creating directory "' . $dirName . '"...' . PHP_EOL;
            $dirPath = $this->projectSourcePath . $dirName . '/';
            mkdir($dirPath);

            foreach ($classes as $class)
            {
                echo 'Creating class "' . $dirName . '/' . $class->name . '"' . PHP_EOL;
                $this->createClass($dirPath, $class);
            }
        }

        // Create the main.js file
        $this->createMainJsFile();
    }

    public function createClass($dirPath, $class)
    {
        $props = $this->getConstructorString($class->properties);
        $methods = $this->getMethodsString($class->methods);

        $from = [
            '[_CLASSNAME_]',
            '[_PROPS_]',
            '[_METHODS_]',
            '[_PROJECT_NAME_]',
            '[_AUTHOR_]'
        ];

        $to = [
            $class->name,
            $props,
            $methods,
            $this->projectName,
            $this->author
        ];

        $classFilePathToWriteTo = $dirPath . $class->name . '.js';
        $formatted = str_replace($from, $to, $this->classTemplate);
        file_put_contents($classFilePathToWriteTo, $formatted);

        $this->classesToImportToMain[] = basename($dirPath) . '/' . $class->name;
    }

    public function getConstructorString($props)
    {
        if (empty($props))
        {
            return '';
        }

        $string = '';
        $string .= $this->tab . 'constructor() {' . PHP_EOL;

        foreach ($props as $prop)
        {
            if (strstr($prop, ':'))
            {
                $bits = explode(':', $prop);
                $propertyName = $bits[0];
                $propertyType = $bits[1];
            }
            else
            {
                $propertyName = $prop;
                $propertyType = null;
            }

            $string .= $this->tab . $this->tab . 'this.' . $propertyName . ' = ';

            switch ($propertyType)
            {
                case 'string':
                    $string .= "''";
                    break;

                case 'object':
                    $string .= "{}";
                    break;

                case 'array':
                    $string .= "[]";
                    break;

                case 'boolean':
                    $string .= "false";
                    break;

                default:
                    $string .= "null";
                    break;
            }

            $string .= ';' . PHP_EOL;
        }

        $string .= $this->tab . '}' . PHP_EOL;

        return $string;
    }

    public function getMethodsString($methods)
    {
        $string = '';

        if (empty($methods))
        {
            return $string;
        }

        foreach ($methods as $method)
        {
            $string .= $this->tab . $method . '() {' . PHP_EOL;
            $string .= $this->tab . $this->tab . '// ...' . PHP_EOL;
            $string .= $this->tab . '}' . PHP_EOL . PHP_EOL;
        }

        return $string;
    }

    public function createMainJsFile()
    {
        $from = [
            '[_PROJECT_NAME_]',
            '[_AUTHOR_]',
            '[_IMPORTS_]'
        ];

        $to = [
            $this->projectName,
            $this->author,
            $this->getMainImportsString()
        ];

        $destinationPath = $this->projectSourcePath . 'main.js';
        $template = file_get_contents(__DIR__ . '/templates/' . 'main.js');
        $code = str_replace($from, $to, $template);
        file_put_contents($destinationPath, $code);
    }

    public function getMainImportsString()
    {
        $string = '';

        if (empty($this->classesToImportToMain))
        {
            return $string;
        }

        foreach ($this->classesToImportToMain as $classPath)
        {
            $string .= 'import ' . basename($classPath) . ' from \'./' . $classPath . '\';' . PHP_EOL;
        }

        return $string;
    }

    public function copyBoilerplateCode()
    {
        $sourcePath = __DIR__ . '/boilerplate/*';
        $destinationPath = $this->projectPath;
        exec('cp -r ' . $sourcePath . ' ' . $destinationPath);

        $this->replaceTokensInGulpfile();
    }

    public function replaceTokensInGulpfile()
    {
        $gulpFilePath = $this->projectPath . 'gulpfile.js';
        $contents = file_get_contents($gulpFilePath);
        $from = [
            '[_DOMAIN_]'
        ];
        $to = [
            $this->domain
        ];
        file_put_contents($gulpFilePath, str_replace($from, $to, $contents));
    }
}
