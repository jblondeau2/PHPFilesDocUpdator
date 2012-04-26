<?php

/**
 * PHPFilesDocUpdator class.
 * 
 * @package PHPFilesDocUpdator
 * 
 * @author Cédric Dugat <c.dugat@groupe-highco.com>
 * @version 1.0
 */
class PHPFilesDocUpdator
{
    /**
      * @var array $options 
      */
    protected $options;

    /**
      * @var array $filesTypeAssoc 
      */
    protected $filesTypeAssoc = array();

    /**
      * @var string $pathToParse 
      */
    protected $pathToParse;

    /**
      * @var array $files 
      */
    protected $files;

    /**
      * @var array $parsedFilesLogs 
      */
    protected $parsedFilesLogs;

    /**
      * @var array $changesLogs 
      */
    protected $changesLogs;

    /**
      * @var string $phpDocPattern 
      */
    protected $phpDocPattern = '/@([a-z]+)\s+(.*?)\s*(?=$|@[a-z]+\s)/';

    /**
     * Constructor.
     * 
     * @param string $pathToParse Path to parse
     * @param array  $options Options
     * @param array  $filesTypeAssoc Files type association
     */
    public function __construct($pathToParse, array $options = array(), array $filesTypeAssoc = array())
    {
        $defaultOptions = array(
            'withSubPackages' => false,
            'realMode'        => true,
        );

        $this->options         = array_merge($defaultOptions, $options);
        $this->filesTypeAssoc  = $filesTypeAssoc;
        $this->pathToParse     = $pathToParse;
        $this->files           = array();
        $this->parsedFilesLogs = array();
        $this->changesLogs     = array();
    }

    /**
     * Let's go.
     */
    public function letsGo()
    {
        foreach ($this->getFilesFromGlob($this->pathToParse.'/*.php') as $filePath)
        {
            $this->files[] = $this->getFileInformations($filePath);
        }

        foreach ($this->files as $f)
        {
            $this->parsedFilesLogs[] = sprintf('%s [%s]',
                $f['filePath'],
                strtoupper($f['fileType'])
            );

            if (isset($this->options['packageName']) && $this->options['packageName'])
            {
                $this->applyChange($f, 'package', $this->options['packageName']);
            }
            
            if (empty($this->options['packageName']))
            {
                /**
                 * Check if file is a plugin/bundle/other one (for example symfony/Symfony projects).
                 */
                if (preg_match('/(\w*)(Plugin|Bundle)\//', $f['filePath'], $matches))
                {
                    $this->applyChange($f, 'package', $matches[1].$matches[2]);
                }
            }

            if (isset($this->options['withSubPackages']) && $this->options['withSubPackages'])
            {
                $this->applyChange($f, 'subpackage', $this->getFileTypeFromPath($f['filePath']));
            }

            if (isset($this->options['authorName']) && $this->options['authorName'])
            {
                $this->applyChange($f, 'author', $this->options['authorName']);
            }

            if (isset($this->options['version']) && $this->options['version'])
            {
                $this->applyChange($f, 'version', $this->options['version']);
            }
        }
    }

    /**
     * Apply PHP documentation change to a file.
     * 
     * @param array  $file Concerned file
     * @param string $key PHPDoc key
     * @param string $value New PHPDoc value
     */
    protected function applyChange(array $file, $key, $value)
    {
        if (isset($file['phpDoc'][$key]) && $file['phpDoc'][$key])
        {
            $newLine = str_replace($file['phpDoc'][$key]['value'], $value, $file['phpDoc'][$key]['originalLine']);

            if ($this->options['realMode'])
            {
                $processResult = $this->replaceLines($file['filePath'], array($file['phpDoc'][$key]['lineNumber'] => $newLine)) ? '[OK]' : '[NOK]';
            }
            else
            {
                $processResult = '[NOT-REAL-MODE]';
            }

            $this->changesLogs[] = sprintf('[%s] %s / %s -> %s %s',
                strtoupper($key),
                $file['filePath'],
                $file['phpDoc'][$key]['value'],
                $value,
                $processResult
            );
        }
    }

    /**
     * Get file informations from file path.
     * 
     * @param string $filePath
     * 
     * @return array
     */
    protected function getFileInformations($filePath)
    {
        $fileTypeFromPath = $this->getFileTypeFromPath($filePath);
        $fileParsedLines  = $this->getParsedLines($filePath);

        return array(
            'filePath' => $filePath,
            'fileType' => $this->getFileTypeFromPath($filePath),
            'phpDoc'   => $fileParsedLines,
        );
    }

    /**
     * Get files list from pattern, using glob() PHP function like recursive one.
     * 
     * @param string $pattern Pattern
     * @param int $flags Flags
     * 
     * @return array
     */
    protected function getFilesFromGlob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
        {
            $files = array_merge($files, $this->getFilesFromGlob($dir.'/'.basename($pattern), $flags));
        }

        return $files;
    }

    /**
     * Get file type from complete path.
     * 
     * @param string $filePath Complete file path
     * 
     * @return string
     */
    protected function getFileTypeFromPath($filePath)
    {
        $fileExploded = explode('/', $filePath);

        foreach ($this->filesTypeAssoc as $keyword => $type)
        {
            if (in_array($keyword, $fileExploded))
            {
                return $type;
            }
        }

        return isset($this->filesTypeAssoc['na']) ? $this->filesTypeAssoc['na'] : 'other';
    }

    /**
     * Get parsed first lines.
     * From phpDoc comments.
     * Only returns important/completed lines.
     * 
     * @param string $filePath File path
     * @param int    $limitCount Lines count limit
     * 
     * @return array
     */
    protected function getParsedLines($filePath, $limitCount = 10)
    {
        $fileFirstLines = file($filePath);
        $parsedLines = array();

        for ($i = 0; $i <= $limitCount; $i++)
        {
            if (isset($fileFirstLines[$i]) && strlen($fileFirstLines[$i]) > 0)
            {
                preg_match($this->phpDocPattern, $fileFirstLines[$i], $matches);

                if (isset($matches[1]) && isset($matches[2]))
                {
                    $parsedLines[$matches[1]] = array(
                        'value'        => preg_replace(array('/\n/', '/\r/'), array('', ''), $matches[2]),
                        'originalLine' => preg_replace(array('/\n/', '/\r/'), array('', ''), $fileFirstLines[$i]),
                        'lineNumber'   => $i + 1,
                    );
                }
            }
        }

        return $parsedLines;
    }

    /**
     * Replace lines.
     * 
     * @param string $filePath File path
     * @param array  $newLines New lines to insert
     * @param string $sourceFile Source file
     */
    protected function replaceLines($filePath, $newLines, $sourceFile = null)
    {
        $response = false;
        $tab      = chr(9);
        $lbreak   = chr(13).chr(10);

        if ($sourceFile)
        {
            $lines = file($sourceFile);
        }
        else
        {
            $lines = file($filePath);
        }

        foreach ($newLines as $key => $value)
        {
            $lines[--$key] = $value.$lbreak;
        }

        $newContent = implode('', $lines);

        if ($h = fopen($filePath, 'w'))
        {
            if (fwrite($h, $newContent))
            {
                $response = true;
            }

            fclose($h);
        }

        return $response;
    }

    /**
      * Get ParsedFilesLogs value.
      *
      * @return array ParsedFilesLogs value to get
      */
    public function getParsedFilesLogs()
    {
        return $this->parsedFilesLogs;
    }

    /**
      * Get ChangesLogs value.
      *
      * @return array ChangesLogs value to get
      */
    public function getChangesLogs()
    {
        return $this->changesLogs;
    }
}