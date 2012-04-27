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
            'realMode'        => true,
            'phpDoc'          => array(),
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
        foreach ($this->getFilesFromGlob($this->pathToParse.'/*.php') as $k => $filePath)
        {
            $this->files[] = $this->getFileInformations($filePath);
            $this->files[$k]['key'] = $k;
        }

        foreach ($this->files as $f)
        {
            $this->parsedFilesLogs[] = sprintf('%s [%s]',
                $f['filePath'],
                strtoupper($f['fileType'])
            );

            foreach ($this->options['phpDoc'] as $k => $v)
            {
                switch ($k)
                {
                    default:
                        $this->applyChange($f, $k, $v);

                        break;

                    case 'package':
                        if (!$v && preg_match('/(\w*)(Plugin|Bundle)\//', $f['filePath'], $matches))
                        {
                            $this->applyChange($f, $k, $matches[1].$matches[2]);
                        }
                        else
                        {
                            $this->applyChange($f, $k, $v);
                        }

                        break;

                    case 'subpackage':
                        if (!$v)
                        {
                            $this->applyChange($f, $k, $this->getFileTypeFromPath($f['filePath']));
                        }
                        else
                        {
                            $this->applyChange($f, $k, $v);
                        }

                        break;
                }
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
        $file = $this->files[$file['key']];

        $spacesBetweenKeyAndValue = '';

        for ($i = strlen($key); $i <= 12; $i++)
        {
            $spacesBetweenKeyAndValue .= ' ';
        }

        if (count($file['phpDoc']) == 0)
        {
            $linesToAdd  = "\n";
            $linesToAdd .= "/**\n";
            $linesToAdd .= "  * @$key$spacesBetweenKeyAndValue$value";
            $linesToAdd .= "\n";
            $linesToAdd .= "  */";

            if ($this->options['realMode'])
            {
                $processResult = $this->replaceLines($file['filePath'], array(2 => $linesToAdd));
            }

            $processType = '*';
        }
        elseif (isset($file['phpDoc'][$key]) && $file['phpDoc'][$key])
        {
            $newLine = str_replace($file['phpDoc'][$key]['value'], $value, $file['phpDoc'][$key]['originalLine']);

            if ($this->options['realMode'])
            {
                $processResult = $this->replaceLines($file['filePath'], array($file['phpDoc'][$key]['lineNumber'] => $newLine));
            }

            $processType = 'UPDATED';
        }
        else
        {
            $phpDocItems = array();

            foreach ($file['phpDoc'] as $k => $dData)
            {
                $phpDocItems[] = array('key' => $k);
            }

            $itemInfos    = $phpDocItems[count($phpDocItems) - 1];
            $lineNumber   = $file['phpDoc'][$itemInfos['key']]['lineNumber'];
            $originalLine = $file['phpDoc'][$itemInfos['key']]['originalLine'];
            $lineToAdd    = "  * @$key$spacesBetweenKeyAndValue$value";

            if ($this->options['realMode'])
            {
                $processResult = $this->replaceLines($file['filePath'], array($lineNumber => $originalLine."\n\r".$lineToAdd));
            }

            $processType = 'CREATED';
        }

        if (isset($processResult) && $this->options['realMode'])
        {
            $processResult = $processResult ? '[OK]' : '[NOK]';
        }
        else
        {
            $processResult = '[NOT-REAL-MODE]';
        }

        $this->changesLogs[] = sprintf('[%s-%s] %s / %s -> %s %s',
            strtoupper($key),
            $processType,
            $file['filePath'],
            isset($file['phpDoc'][$key]['value']) ? $file['phpDoc'][$key]['value'] : '---',
            $value,
            $processResult
        );

        $this->files[$file['key']]['phpDoc'] = $this->getParsedLines($file['filePath']);
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
            $lineToParse = preg_replace(array('/\n/', '/\r/'), array('', ''), $fileFirstLines[$i]);

            if (isset($lineToParse) && strlen($lineToParse) > 0)
            {
                /**
                 * Only parse first commented lines.
                 * Stop parsing if PHP code starts.
                 */
                if ($lineToParse && !in_array(substr(trim($lineToParse), 0, 1), array('<', '*', '/')))
                {
                    break;
                }

                preg_match($this->phpDocPattern, $lineToParse, $matches);

                if (isset($matches[1]) && isset($matches[2]))
                {
                    $parsedLines[$matches[1]] = array(
                        'value'        => $matches[2],
                        'originalLine' => $lineToParse,
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
