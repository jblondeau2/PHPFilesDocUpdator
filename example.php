<?php

require_once dirname(__FILE__).'/PHPFilesDocUpdator.class.php';

/**
 * Just associate a present path key to a subpackage to apply.
 * Example for a symfony 1.4 project.
 */
$filesTypeAssoc = array(
    'model'     => 'Model',
    'form'      => 'Form',
    'filter'    => 'Filter',
    'actions'   => 'Action',
    'validator' => 'Validator',
    'helper'    => 'Helper',
    'na'        => 'Other',
);

/**
 * Folders to parse, with informations.
 * Adapt it to your project.
 */
$foldersToParse = array(
    array('apps/frontend/lib',      'Frontend'),
    array('apps/frontend/modules',  'Frontend'),
    array('lib',                    'Custom'),
    array('plugins/myPlugin',       null),
);

foreach ($foldersToParse as $f)
{
    /**
     * Options to apply.
     * These informations are to be completed to change them.
     *
     * realMode: set to 'true' if you want to apply modifications to files
     * generateDescription: generate class description if phpDoc headers have to be created
     * phpDoc: an array with phpDoc options to set
     */
    $options = array(
        'realMode'            => true,
        'generateDescription' => true,
        'phpDoc'              => array(
            'package'    => 'Poney',
            'subpackage' => null,
            'version'    => '1.23',
            'author'     => 'Chuck Norris <chuck@norr.is>',
        ),
    );

    $phpDocCheater = new PHPFilesDocUpdator($f[0], $options, $filesTypeAssoc);
    $phpDocCheater->letsGo();

    /**
     * -------------------------------------------
     * With displaying some events...
     * -------------------------------------------
     */
    displayLog($phpDocCheater->getParsedFilesLogs(), 'PARSING');
    displayLog($phpDocCheater->getChangesLogs(),     'CHANGES');
}

/**
 * A fast example/function to display logs returned from class.
 */
function displayLog($content, $title)
{
    echo "\n----------------------\n".$title."\n----------------------\n";

    if (count($content))
    {
        foreach ($content as $l)
        {
            echo "    ".$l."\n";
        }

        echo "\n----------------------\nEntries: ".count($content)."\n----------------------\n";
    }
    else
    {
        echo "No entry.\n";
    }
}