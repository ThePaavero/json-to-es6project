<?php

/**
 * Class CodeGenerator
 *
 * @author Pekka <pekka@astudios.org>
 */
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
    public $sassIncludes = [];

    /**
     * CodeGenerator constructor.
     *
     * @param $templateName
     */
    public function __construct($templateName)
    {
        $this->templateName = $templateName;
        $this->templateFilePath = $this->templateDirectory . $this->templateName . '.json';
    }

    /**
     * Do we have a valid template file path?
     *
     * @return bool
     */
    public function templateJsonFileExists()
    {
        return file_exists($this->templateFilePath);
    }

    /**
     * Endpoint for our operations.
     *
     * @throws Exception
     */
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

    /**
     * Generate our files.
     *
     * @param $data
     */
    public function createFiles($data)
    {
        $this->projectName = $data->projectName;
        $this->author = $data->author;
        $this->domain = $data->domain;
        $this->sassIncludes = $data->sassIncludes;

        $generatedDirPath = __DIR__ . '/../generated/';

        if ( ! is_dir($generatedDirPath))
        {
            mkdir($generatedDirPath);
        }

        $this->projectPath = $generatedDirPath . $data->projectName . '/';
        $this->projectSourcePath = $this->projectPath . 'src/';

        if (is_dir($this->projectPath))
        {
            die('Project directory already exists, aborting!' . PHP_EOL);
        }

        echo 'Creating project directory in /generated/' . $this->projectName . PHP_EOL;
        mkdir($this->projectPath);
        mkdir($this->projectPath . 'src');
        mkdir($this->projectSourcePath . 'js');
        mkdir($this->projectSourcePath . 'scss');

        // Copy our JSPM stuff
        $this->copyBoilerplateCode();

        // Modify our gulpfile template
        $this->replaceTokensInGulpfile();

        $this->classTemplate = file_get_contents(__DIR__ . '/templates/class.js');

        foreach ($data->classes as $dirName => $classes)
        {
            echo 'Creating directory "' . $dirName . '"...' . PHP_EOL;
            $dirPath = $this->projectSourcePath . 'js/' . $dirName . '/';
            mkdir($dirPath);

            foreach ($classes as $class)
            {
                echo 'Creating class "' . $dirName . '/' . $class->name . '"' . PHP_EOL;
                $this->createClass($dirPath, $class);
            }
        }

        // Create the main.js file
        $this->createMainJsFile();

        $this->createSassIncludes();
    }

    /**
     * Create a JS class file and its code.
     *
     * @param $dirPath
     * @param $class
     */
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

    /**
     * Get the first part of our JS class. The constructor method
     * with optional properties.
     *
     * @param $props
     * @return string
     */
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

    /**
     * Get a string with a list of boilerplate methods.
     *
     * @param $methods
     * @return string
     */
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

    /**
     * Create our main.js file.
     */
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

        $destinationPath = $this->projectSourcePath . 'js/main.js';
        $template = file_get_contents(__DIR__ . '/templates/' . 'main.js');
        $code = str_replace($from, $to, $template);
        file_put_contents($destinationPath, $code);
    }

    /**
     * Get import JS lines for our main.js file.
     *
     * @return string
     */
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

    /**
     * Copy our boilerplate code.
     */
    public function copyBoilerplateCode()
    {
        $sourcePath = __DIR__ . '/boilerplate/*';
        $destinationPath = $this->projectPath;
        exec('cp -r ' . $sourcePath . ' ' . $destinationPath);
    }

    /**
     * Replace tokens in our gulpfile.
     */
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

    /**
     * Create our sass files. Also add their @import lines to the main project.scss file.
     */
    public function createSassIncludes()
    {
        if (empty($this->sassIncludes))
        {
            return;
        }

        $importString = '';

        foreach ($this->sassIncludes as $include)
        {
            $scssBoilerplateCode = '// ...';

            $writeTo = $this->projectSourcePath . 'scss/' . $include . '.scss';
            file_put_contents($writeTo, $scssBoilerplateCode);
            echo 'Created sass include "' . $include . '"' . PHP_EOL;

            $importString .= '@import "' . str_replace('/_', '/', $include) . '";' . PHP_EOL;
        }

        $projectScssFilePath = $this->projectSourcePath . 'scss/project.scss';
        $contents = file_get_contents($projectScssFilePath);

        $from = [
            '[_INCLUDES_]'
        ];
        $to = [
            $importString
        ];

        file_put_contents($projectScssFilePath, str_replace($from, $to, $contents));

        echo 'Added @imports for all includes.' . PHP_EOL;
    }
}
